<?php

/**
 * @file plugins/generic/snrd/SNRDPlugin.inc.php
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SNRDPlugin
 * @ingroup plugins_generic_snrd
 *
 * @brief SNRD plugin class
 */

define('SNRD_ACCESS_OPEN', 0);
define('SNRD_ACCESS_CLOSED', 1);
define('SNRD_ACCESS_EMBARGOED', 2);
define('SNRD_ACCESS_DELAYED', 3);
define('SNRD_ACCESS_RESTRICTED', 4);

import('lib.pkp.classes.plugins.GenericPlugin');

class SNRDPlugin extends GenericPlugin {

	/**
	 * Called as a plugin is registered to the registry
	 * @param $category String Name of category plugin was registered to
	 * @return boolean True if plugin initialized successfully; if false,
	 * 	the plugin will not be registered.
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		if ($success && $this->getEnabled()) {
			$this->import('SNRDDAO');
			$snrdDao = new SNRDDAO();
			DAORegistry::registerDAO('SNRDDAO', $snrdDao);

			// Add SNRD set to OAI results
			HookRegistry::register('OAIDAO::getJournalSets', array($this, 'sets'));
			//HookRegistry::register('JournalOAI::records', array($this, 'recordsOrIdentifiers'));
			HookRegistry::register('JournalOAI::identifiers', array($this, 'recordsOrIdentifiers'));
			//HookRegistry::register('OAIDAO::_returnRecordFromRow', array($this, 'addSet'));
			HookRegistry::register('OAIDAO::_returnIdentifierFromRow', array($this, 'addSet'));

			// consider SNRD article in article tombstones
			//HookRegistry::register('ArticleTombstoneManager::insertArticleTombstone', array($this, 'insertSNRDArticleTombstone'));
		}
		return $success;
	}

	function getDisplayName() {
		return __('plugins.generic.snrd.displayName');
	}

	function getDescription() {
		return __('plugins.generic.snrd.description');
	}

	/*
	 * OAI interface
	 */

	/**
	 * Add SNRD set
	 */
	function sets($hookName, $params) {
		$sets =& $params[5];
		array_push($sets, new OAISet('snrd', 'set SNRD', ''));
		return false;
	}

	/**
	 * Get SNRD records or identifiers
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
			$snrdDao =& DAORegistry::getDAO('SNRDDAO');
			$snrdDao->setOAI($journalOAI);
			if ($hookName == 'JournalOAI::records') {
				$funcName = '_returnRecordFromRow';
			} else if ($hookName == 'JournalOAI::identifiers') {
				$funcName = '_returnIdentifierFromRow';
			}
			$journalId = $journalOAI->journalId;
			$records = $snrdDao->getSNRDRecordsOrIdentifiers(array($journalId, null), $from, $until, $offset, $limit, $total, $funcName);
			return true;
		}
		return false;
	}


	/**
	 * Change OAI record or identifier to consider the SNRD set
	 */
	function addSet($hookName, $params) {
		$record =& $params[0];
		$row = $params[1];

		if ($this->isSNRDRecord($row)) {
			$record->sets[] = 'snrd';
		}
		return false;
	}

	/**
	 * Consider the SNRD article in the article tombstone
	 */
	function insertSNRDArticleTombstone($hookName, $params) {
		$articleTombstone =& $params[0];

		if ($this->isSNRDArticle($articleTombstone->getOAISetObjectId(ASSOC_TYPE_JOURNAL), $articleTombstone->getDataObjectId())) {
			$dataObjectTombstoneSettingsDao =& DAORegistry::getDAO('DataObjectTombstoneSettingsDAO');
			$dataObjectTombstoneSettingsDao->updateSetting($articleTombstone->getId(), 'snrd', true, 'bool');
		}
		return false;
	}

	/**
	 * Check if it's a SNRD record.
	 * @param $row array of database fields
	 * @return boolean
	 */
	function isSNRDRecord($row) {
		// if the article is alive
		if (!isset($row['tombstone_id'])) {
			$journalDao =& DAORegistry::getDAO('JournalDAO');
			$publishedArticleDao =& DAORegistry::getDAO('PublishedArticleDAO');
			$issueDao =& DAORegistry::getDAO('IssueDAO');

			$journal = $journalDao->getById($row['journal_id']);
			$article = $publishedArticleDao->getPublishedArticleByArticleId($row['article_id']);
			$issue = $issueDao->getIssueById($article->getIssueId());

			// is open access
			$status = '';
			$booleann= false;
			if ($journal->getSetting('publishingMode') == PUBLISHING_MODE_OPEN ) {
			    if ($booleann){ 
				$status = SNRD_ACCESS_CLOSED;
				}
			} else if ($journal->getSetting('publishingMode') == PUBLISHING_MODE_SUBSCRIPTION) {
				if ($issue->getAccessStatus() == 0 || $issue->getAccessStatus() == ISSUE_ACCESS_OPEN) {
					$status = SNRD_ACCESS_OPEN;
				} else if ($issue->getAccessStatus() == ISSUE_ACCESS_SUBSCRIPTION) {
					if (is_a($article, 'PublishedArticle') && $article->getAccessStatus() == ARTICLE_ACCESS_OPEN) {
						$status = SNRD_ACCESS_OPEN;
					} else if ($issue->getAccessStatus() == ISSUE_ACCESS_SUBSCRIPTION && $issue->getOpenAccessDate() != NULL) {
						$status = SNRD_ACCESS_EMBARGOED;
					} else if ($issue->getAccessStatus() == ISSUE_ACCESS_SUBSCRIPTION && $issue->getOpenAccessDate() == NULL) {
						$status = SNRD_ACCESS_CLOSED;
					}
				}
			}
			if ($journal->getSetting('restrictSiteAccess') == 1 || $journal->getSetting('restrictArticleAccess') == 1) {
				$status = SNRD_ACCESS_RESTRICTED;
			}

			if ($status == SNRD_ACCESS_EMBARGOED && date('Y-m-d') >= date('Y-m-d', strtotime($issue->getOpenAccessDate()))) {
				$status = SNRD_ACCESS_DELAYED;
			}

			// is there a full text
			$galleys =& $article->getGalleys();
			if (!empty($galleys)) {
				return $status == SNRD_ACCESS_OPEN;
			}
			return false;
		} else {
			$dataObjectTombstoneSettingsDao =& DAORegistry::getDAO('DataObjectTombstoneSettingsDAO');
			return $dataObjectTombstoneSettingsDao->getSetting($row['tombstone_id'], 'snrd');
		}
	}


	/**
	 * Check if it's a SNRD article.
	 * @param $row ...
	 * @return boolean
	 */
	function isSNRDArticle($journalId, $articleId) {
			$journalDao =& DAORegistry::getDAO('JournalDAO');
			$publishedArticleDao =& DAORegistry::getDAO('PublishedArticleDAO');
			$issueDao =& DAORegistry::getDAO('IssueDAO');

			$journal = $journalDao->getById($journalId);
			$article = $publishedArticleDao->getPublishedArticleByArticleId($articleId);
			$issue = $issueDao->getIssueById($article->getIssueId());

			// is open access
			$status = '';
			if ($journal->getSetting('publishingMode') == PUBLISHING_MODE_OPEN) {
				$status = SNRD_ACCESS_OPEN;
			} else if ($journal->getSetting('publishingMode') == PUBLISHING_MODE_SUBSCRIPTION) {
				if ($issue->getAccessStatus() == 0 || $issue->getAccessStatus() == ISSUE_ACCESS_OPEN) {
					$status = SNRD_ACCESS_OPEN;
				} else if ($issue->getAccessStatus() == ISSUE_ACCESS_SUBSCRIPTION) {
					if (is_a($article, 'PublishedArticle') && $article->getAccessStatus() == ARTICLE_ACCESS_OPEN) {
						$status = SNRD_ACCESS_OPEN;
					} else if ($issue->getAccessStatus() == ISSUE_ACCESS_SUBSCRIPTION && $issue->getOpenAccessDate() != NULL) {
						$status = SNRD_ACCESS_EMBARGOED;
					} else if ($issue->getAccessStatus() == ISSUE_ACCESS_SUBSCRIPTION && $issue->getOpenAccessDate() == NULL) {
						$status = SNRD_ACCESS_CLOSED;
					}
				}
			}
			if ($journal->getSetting('restrictSiteAccess') == 1 || $journal->getSetting('restrictArticleAccess') == 1) {
				$status = SNRD_ACCESS_RESTRICTED;
			}

			if ($status == SNRD_ACCESS_EMBARGOED && date('Y-m-d') >= date('Y-m-d', strtotime($issue->getOpenAccessDate()))) {
				$status = SNRD_ACCESS_DELAYED;
			}

			// is there a full text
			$galleys =& $article->getGalleys();
			if (!empty($galleys)) {
				return $status == SNRD_ACCESS_OPEN;
			}
			return false;
	}

}
?>
