<?php

/**
 * @file plugins/generic/openSNRD/OpenSNRDPlugin.inc.php
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OpenSNRDPlugin
 * @ingroup plugins_generic_openSNRD
 *
 * @brief OpenSNRD plugin class
 */

import('lib.pkp.classes.plugins.GenericPlugin');


class OpenSNRDPlugin extends GenericPlugin {

	/**
	 * Called as a plugin is registered to the registry
	 * @param $category String Name of category plugin was registered to
	 * @return boolean True if plugin initialized successfully; if false,
	 * 	the plugin will not be registered.
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		//$this->addLocaleData();
		if ($success && $this->getEnabled()) {
			$this->import('OpenSNRDDAO');
			$openSNRDDao = new OpenSNRDDAO();
			DAORegistry::registerDAO('OpenSNRDDAO', $openSNRDDao);


			// Insert new field into author metadata submission form (submission step 3) and metadata form
			HookRegistry::register('Templates::Author::Submit::AdditionalMetadata', array($this, 'metadataFieldEdit'));
			HookRegistry::register('Templates::Submission::MetadataEdit::AdditionalMetadata', array($this, 'metadataFieldEdit'));
			// Consider the new field in the metadata view
			HookRegistry::register('Templates::Submission::Metadata::Metadata::AdditionalMetadata', array($this, 'metadataFieldView'));

			// Hook for initData in two forms -- init the new field
			HookRegistry::register('metadataform::initdata', array($this, 'metadataInitData'));
			HookRegistry::register('authorsubmitstep3form::initdata', array($this, 'metadataInitData'));

			// Hook for readUserVars in two forms -- consider the new field entry
			HookRegistry::register('metadataform::readuservars', array($this, 'metadataReadUserVars'));
			HookRegistry::register('authorsubmitstep3form::readuservars', array($this, 'metadataReadUserVars'));

			// Hook for execute in two forms -- consider the new field in the article settings
			HookRegistry::register('authorsubmitstep3form::execute', array($this, 'metadataExecute'));
			HookRegistry::register('metadataform::execute', array($this, 'metadataExecute'));

			// Hook for save in two forms -- add validation for the new field
			HookRegistry::register('authorsubmitstep3form::Constructor', array($this, 'addCheck'));
			HookRegistry::register('metadataform::Constructor', array($this, 'addCheck'));

			// Consider the new field for ArticleDAO for storage
			HookRegistry::register('articledao::getAdditionalFieldNames', array($this, 'articleSubmitGetFieldNames'));

			// Add OpenSNRD set to OAI results
			HookRegistry::register('OAIDAO::getJournalSets', array($this, 'sets'));
			HookRegistry::register('JournalOAI::identifiers', array($this, 'recordsOrIdentifiers'));
			HookRegistry::register('JournalOAI::records', array($this, 'recordsOrIdentifiers'));
			HookRegistry::register('OAIDAO::_returnRecordFromRow', array($this, 'addSet'));
			HookRegistry::register('OAIDAO::_returnIdentifierFromRow', array($this, 'addSet'));

			 // Change Dc11Desctiption -- consider OpenSNRD elements relation, rights and date
			HookRegistry::register('Dc11SchemaArticleAdapter::extractMetadataFromDataObject', array($this, 'changeDc11Desctiption'));

			// consider OpenSNRD articles in article tombstones
			HookRegistry::register('ArticleTombstoneManager::insertArticleTombstone', array($this, 'insertOpenSNRDArticleTombstone'));

		}
		return $success;
	}

	function getDisplayName() {
		return __('plugins.generic.openSNRD.displayName');
	}

	function getDescription() {
		return __('plugins.generic.openSNRD.description');
	}

	/*
	 * Metadata
	 */

	/**
	 * Insert snrdID field into author submission step 3 and metadata edit form
	 */
	function metadataFieldEdit($hookName, $params) {
		$smarty =& $params[1];
		$output =& $params[2];

		$output .= $smarty->fetch($this->getTemplatePath() . 'snrdIDEdit.tpl');
		return false;
	}

	/**
	 * Add snrdID to the metadata view
	 */
	function metadataFieldView($hookName, $params) {
		$smarty =& $params[1];
		$output =& $params[2];

		$output .= $smarty->fetch($this->getTemplatePath() . 'snrdIDView.tpl');
		return false;
	}

	/**
	 * Add snrdID element to the article
	 */
	function articleSubmitGetFieldNames($hookName, $params) {
		$fields =& $params[1];
		$fields[] = 'snrdID';
		return false;
	}

	/**
	 * Set article snrdID
	 */
	function metadataExecute($hookName, $params) {
		$form =& $params[0];
		$article =& $form->article;
		$formProjectID = $form->getData('snrdID');
		$article->setData('snrdID', $formProjectID);
		return false;
	}

	/**
	 * Add check/validation for the snrdID field (= 6 numbers)
	 */
	function addCheck($hookName, $params) {
		$form =& $params[0];
		if (get_class($form) == 'AuthorSubmitStep3Form' || get_class($form) == 'MetadataForm' ) {
			$form->addCheck(new FormValidatorRegExp($form, 'snrdID', 'optional', 'plugins.generic.openSNRD.snrdIDValid', '/^\d{6}$/'));
		}
		return false;
	}

	/**
	 * Init article snrdID
	 */
	function metadataInitData($hookName, $params) {
		$form =& $params[0];
		$article =& $form->article;
		$articleProjectID = $article->getData('snrdID');
		$form->setData('snrdID', $articleProjectID);
		return false;
	}

	/**
	 * Concern snrdID field in the form
	 */
	function metadataReadUserVars($hookName, $params) {
		$userVars =& $params[1];
		$userVars[] = 'snrdID';
		return false;
	}


	/*
	 * OAI interface
	 */

	/**
	 * Add OpenSNRD set
	 */
	function sets($hookName, $params) {
		$sets =& $params[5];
		array_push($sets, new OAISet('snrd', 'open SNRD', ''));
		return false;
	}

	/**
	 * Get OpenSNRD records or identifiers
	 */
	function recordsOrIdentifiers($hookName, $params) {
		$journalOAI =& $params[0];
		$from = $params[1];
		$until = $params[2];
		$set = $params[3];
		$offset = $params[4];
		$limit = $params[5];
		$total = $params[6];
		$records =& $params[7];

		$records = array();
		if (isset($set) && $set == 'snrd') {
			$openSNRDDao =& DAORegistry::getDAO('OpenSNRDDAO');
			$openSNRDDao->setOAI($journalOAI);
			if ($hookName == 'JournalOAI::records') {
				$funcName = '_returnRecordFromRow';
			} else if ($hookName == 'JournalOAI::identifiers') {
				$funcName = '_returnIdentifierFromRow';
			}
			$journalId = $journalOAI->journalId;
			$records = $openSNRDDao->getOpenSNRDRecordsOrIdentifiers(array($journalId, null), $from, $until, $offset, $limit, $total, $funcName);
			return true;
		}
		return false;
	}

	/**
	 * Change OAI record or identifier to consider the OpenSNRD set
	 */
	function addSet($hookName, $params) {
		$record =& $params[0];
		$row = $params[1];

		$openSNRDDao =& DAORegistry::getDAO('OpenSNRDDAO');
		if ($openSNRDDao->isOpenSNRDRecord($row)) {
			$record->sets[] = 'snrd';
		}
		return false;
	}

	/**
	 * Change Dc11 Description to consider the OpenSNRD elements
	 */
	function changeDc11Desctiption($hookName, $params) {
		$adapter =& $params[0];
		$article = $params[1];
		$journal = $params[2];
		$issue = $params[3];
		$dc11Description =& $params[4];

		$openSNRDDao =& DAORegistry::getDAO('OpenSNRDDAO');
		$openSNRDDao->setOAI($journalOAI);
		if ($openSNRDDao->isOpenSNRDArticle($article->getId())) {

			// Determine OpenSNRD DC elements values
			// OpenSNRD DC Relation
			$articleProjectID = $article->getData('snrdID');
			$openSNRDRelation = $articleProjectID;

			// OpenSNRD DC Rights
			$openSNRDRights = 'info:eu-repo/semantics/';
			$status = '';
			if ($journal->getSetting('publishingMode') == PUBLISHING_MODE_OPEN) {
				$status = 'openAccess';
			} else if ($journal->getSetting('publishingMode') == PUBLISHING_MODE_SUBSCRIPTION) {
				if ($issue->getAccessStatus() == 0 || $issue->getAccessStatus() == ISSUE_ACCESS_OPEN) {
					$status = 'openAccess';
				} else if ($issue->getAccessStatus() == ISSUE_ACCESS_SUBSCRIPTION) {
					if (is_a($article, 'PublishedArticle') && $article->getAccessStatus() == ARTICLE_ACCESS_OPEN) {
						$status = 'openAccess';
					} else if ($issue->getAccessStatus() == ISSUE_ACCESS_SUBSCRIPTION && $issue->getOpenAccessDate() != NULL) {
						$status = 'embargoedAccess';
					} else if ($issue->getAccessStatus() == ISSUE_ACCESS_SUBSCRIPTION && $issue->getOpenAccessDate() == NULL) {
						$status = 'closedAccess';
					}
				}
			}
			if ($journal->getSetting('restrictSiteAccess') == 1 || $journal->getSetting('restrictArticleAccess') == 1) {
				$status = 'restrictedAccess';
			}
			$openSNRDRights = $openSNRDRights . $status;

			// OpenSNRD DC Date
			$openSNRDDate = null;
			if ($status == 'embargoedAccess') {
				$openSNRDDate = 'info:eu-repo/date/embargoEnd/' . date('Y-m-d', strtotime($issue->getOpenAccessDate()));
			}
						
			
			// Get current DC statements
			$dcRelationValues = array();
			$dcRightsValues = array();
			$dcDateValues = array();
			if ($dc11Description->hasStatement('dc:relation')) {
				$dcRelationValues = $dc11Description->getStatement('dc:relation');
			}
			if ($dc11Description->hasStatement('dc:rights')) {
				$dcRightsValues = $dc11Description->getStatementTranslations('dc:rights');
			}
			if ($dc11Description->hasStatement('dc:date')) {
				$dcDateValues = $dc11Description->getStatement('dc:date');
			}

			
			// Set new DC statements, concerning OpenSNRD
			array_unshift($dcRelationValues, $openSNRDRelation);
			$newDCRelationStatements = array('dc:relation' => $dcRelationValues);
			$dc11Description->setStatements($newDCRelationStatements);

			foreach ($dcRightsValues as $key => $value) {
				array_unshift($value, $openSNRDRights);
				$dcRightsValues[$key] = $value;
			}
			if (!array_key_exists($journal->getPrimaryLocale(), $dcRightsValues)) {
				$dcRightsValues[$journal->getPrimaryLocale()] = array($openSNRDRights);
			}
			$newDCRightsStatements = array('dc:rights' => $dcRightsValues);
			$dc11Description->setStatements($newDCRightsStatements);
			
			//metadatos solicitados por SNRD
			$driverType = 'artículo';
		        $dc11Description->addStatement('dc:type', $driverType, METADATA_DESCRIPTION_UNKNOWN_LOCALE);
		        
		        $driverType = 'Articulo';
		        $dc11Description->addStatement('dc:type', $driverType, METADATA_DESCRIPTION_UNKNOWN_LOCALE);
		        
		        $driverType = 'info:ar-repo/semantics/artículo';
		        $dc11Description->addStatement('dc:type', $driverType, METADATA_DESCRIPTION_UNKNOWN_LOCALE);
		        
		     	if ($openSNRDDate != null) {
				array_unshift($dcDateValues, $openSNRDDate);
				$newDCDateStatements = array('dc:date' => $dcDateValues);
				$dc11Description->setStatements($newDCDateStatements);
			}
		}
		return false;
	}

	/**
	 * Consider the OpenSNRD set in the article tombstone
	 */
	function insertOpenSNRDArticleTombstone($hookName, $params) {
		$articleTombstone =& $params[0];

		$openSNRDDao =& DAORegistry::getDAO('OpenSNRDDAO');
		if ($openSNRDDao->isOpenSNRDArticle($articleTombstone->getDataObjectId())) {
			$dataObjectTombstoneSettingsDao =& DAORegistry::getDAO('DataObjectTombstoneSettingsDAO');
			$dataObjectTombstoneSettingsDao->updateSetting($articleTombstone->getId(), 'opensnrd', true, 'bool');
		}
		return false;
	}


}
?>
