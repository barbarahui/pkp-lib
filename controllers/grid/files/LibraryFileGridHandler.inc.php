<?php

/**
 * @file controllers/grid/files/LibraryFileGridHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class LibraryFileGridHandler
 * @ingroup controllers_grid_files
 *
 * @brief Base class for handling library file grid requests.
 */

import('lib.pkp.classes.controllers.grid.CategoryGridHandler');
import('lib.pkp.controllers.grid.files.LibraryFileGridRow');
import('lib.pkp.controllers.grid.files.LibraryFileGridCategoryRow');
import('lib.pkp.classes.file.LibraryFileManager');


import('lib.pkp.classes.context.LibraryFile');

// Link action & modal classes
import('lib.pkp.classes.linkAction.request.AjaxModal');

class LibraryFileGridHandler extends CategoryGridHandler {
	/** the context for this grid */
	var $_context;

	/** whether or not the grid is editable **/
	var $_canEdit;

	/**
	 * Constructor
	 */
	function LibraryFileGridHandler($dataProvider) {
		parent::CategoryGridHandler($dataProvider);
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR),
			array(
				'fetchGrid', 'fetchCategory', 'fetchRow', // Parent grid-level actions
			)
		);
	}


	//
	// Getters/Setters
	//
	/**
	 * Get the context
	 * @return object context
	 */
	function getContext() {
		return $this->_context;
	}

	/**
	 * Can the user edit/add files in this grid?
	 * @return boolean
	 */
	function canEdit() {
		return $this->_canEdit;
	}

	/**
	 * Set whether or not the user can edit or add files.
	 * @param $canEdit boolean
	 */
	function setCanEdit($canEdit) {
		$this->_canEdit = $canEdit;
	}

	//
	// Overridden template methods
	//

	/*
	 * Configure the grid
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		parent::initialize($request);

		$router = $request->getRouter();
		$this->_context = $router->getContext($request);

		// Set name
		$this->setTitle('manager.publication.submissionDocuments');

		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_COMMON,
			LOCALE_COMPONENT_APP_COMMON,
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_APP_SUBMISSION,
			LOCALE_COMPONENT_PKP_MANAGER
		);

		// Columns
		// Basic grid row configuration
		$this->addColumn($this->getFileNameColumn());

		$router = $request->getRouter();

		// Add grid-level actions
		if ($this->canEdit()) {
			$this->addAction(
				new LinkAction(
					'addFile',
					new AjaxModal(
						$router->url($request, null, null, 'addFile', null, $this->getActionArgs()),
						__('grid.action.addFile'),
						'modal_add_file'
					),
					__('grid.action.addFile'),
					'add'
				)
			);
		}
	}

	//
	// Implement template methods from CategoryGridHandler
	//
	/**
	 * @see CategoryGridHandler::getCategoryRowInstance()
	 */
	function getCategoryRowInstance() {
		return new LibraryFileGridCategoryRow($this->getContext());
	}

	/**
	 * @see GridHandler::loadData()
	 */
	function loadData($request, $filter) {

		$context = $this->getContext();
		$libraryFileManager = new LibraryFileManager($context->getId());
		$fileTypeKeys = $libraryFileManager->getTypeSuffixMap();
		foreach (array_keys($fileTypeKeys) as $key) {
			$data[$key] = $key;
		}
		return $data;
	}

	//
	// Overridden methods from GridHandler
	//
	/**
	 * Get the row handler - override the default row handler
	 * @return LibraryFileGridRow
	 */
	function getRowInstance() {
		return new LibraryFileGridRow($this->canEdit());
	}

	/**
	 * Get an instance of the cell provider for this grid.
	 * @return LibraryFileGridCellProvider
	 */
	function &getFileNameColumn() {
		import('lib.pkp.controllers.grid.files.LibraryFileGridCellProvider');
		$column = new GridColumn(
			'files',
			'grid.libraryFiles.column.files',
			null,
			'controllers/grid/gridCell.tpl',
			new LibraryFileGridCellProvider()
		);

		return $column;
	}

	//
	// Public File Grid Actions
	//
	/**
	 * An action to add a new file
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function addFile($args, $request) {
		$this->initialize($request);
		$router = $request->getRouter();
		$context = $request->getContext();

		$fileForm = $this->_getNewFileForm($context);
		$fileForm->initData();

		$json = new JSONMessage(true, $fileForm->fetch($request));
		return $json->getString();
	}

	/**
	 * Save a new library file.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function saveFile($args, $request) {
		$router = $request->getRouter();
		$context = $request->getContext();
		$user = $request->getUser();

		$fileForm = $this->_getNewFileForm($context);
		$fileForm->readInputData();

		if ($fileForm->validate()) {
			$fileId = $fileForm->execute($user->getId());

			// Let the calling grid reload itself
			return DAO::getDataChangedEvent();
		}

		$json = new JSONMessage(false);
		return $json->getString();
	}

	/**
	 * An action to add a new file
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function editFile($args, $request) {
		$this->initialize($request);
		assert(isset($args['fileId']));
		$fileId = (int) $args['fileId'];

		$router = $request->getRouter();
		$context = $request->getContext();

		$fileForm = $this->_getEditFileForm($context, $fileId);
		$fileForm->initData();

		$json = new JSONMessage(true, $fileForm->fetch($request));
		return $json->getString();
	}

	/**
	 * Save changes to an existing library file.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function updateFile($args, $request) {
		assert(isset($args['fileId']));
		$fileId = (int) $args['fileId'];

		$router = $request->getRouter();
		$context = $request->getContext();

		$fileForm = $this->_getEditFileForm($context, $fileId);
		$fileForm->readInputData();

		if ($fileForm->validate()) {
			$fileForm->execute();

			// Let the calling grid reload itself
			return DAO::getDataChangedEvent();
		}

		$json = new JSONMessage(false);
		return $json->getString();
	}

	/**
	 * Delete a file
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function deleteFile($args, $request) {
		$fileId = isset($args['fileId']) ? $args['fileId'] : null;
		$router = $request->getRouter();
		$press = $router->getContext($request);

		if ($fileId) {
			$libraryFileManager = new LibraryFileManager($press->getId());
			$libraryFileManager->deleteFile($fileId);

			return DAO::getDataChangedEvent();
		}

		$json = new JSONMessage(false);
		return $json->getString();
	}

	/**
	 * Upload a new library file.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function uploadFile($args, $request) {
		$router = $request->getRouter();
		$context = $request->getContext();
		$user = $request->getUser();

		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new TemporaryFileManager();
		$temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());
		if ($temporaryFile) {
			$json = new JSONMessage(true);
			$json->setAdditionalAttributes(array(
				'temporaryFileId' => $temporaryFile->getId()
			));
		} else {
			$json = new JSONMessage(false, __('common.uploadFailed'));
		}

		return $json->getString();
	}

	/**
	 * Returns a specific instance of the new form for this grid.
	 *  Must be implemented by subclasses.
	 * @param $context Press
	 */
	function &_getNewFileForm($context){
		assert(false);
	}

	/**
	 * Returns a specific instance of the edit form for this grid.
	 *  Must be implemented by subclasses.
	 * @param $context Press
	 * @param $fileId int
	 */
	function &_getEditFileForm($context, $fileId){
		assert(false);
	}

	/**
	 * Retrieve the arguments for the 'add file' action.
	 * @return array
	 */
	function getActionArgs() {
		return array();
	}
}

?>
