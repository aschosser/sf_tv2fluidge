<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Torben Hansen <derhansen@gmail.com>, Skyfillers GmbH
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Helper class for handling TV content column migration to Fluid backend layouts
 */
class Tx_SfTvtools_Service_MigrateContentHelper implements t3lib_Singleton {

	/**
	 * @var Tx_SfTvtools_Service_SharedHelper
	 */
	protected $sharedHelper;

	/**
	 * DI for shared helper
	 *
	 * @param Tx_SfTvtools_Service_SharedHelper $sharedHelper
	 * @return void
	 */
	public function injectSharedHelper(Tx_SfTvtools_Service_SharedHelper $sharedHelper) {
		$this->sharedHelper = $sharedHelper;
	}

	/**
	 * Returns an array of all TemplaVoila page templates
	 *
	 * @return array
	 */
	public function getAllTvTemplates() {
		$fields = 'tx_templavoila_tmplobj.uid, tx_templavoila_tmplobj.title';
		$table = 'tx_templavoila_datastructure, tx_templavoila_tmplobj';
		$where = 'tx_templavoila_datastructure.scope=1 AND tx_templavoila_datastructure.uid = tx_templavoila_tmplobj.datastructure
			AND tx_templavoila_datastructure.deleted=0 AND tx_templavoila_tmplobj.deleted=0';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		$templates = array();
		foreach($res as $fce) {
			$templates[$fce['uid']] = $fce['title'];
		}

		return $templates;
	}

	/**
	 * Returns an array of all Grid Elements
	 *
	 * @return array
	 */
	public function getAllBeLayouts() {
		$fields = 'uid, title';
		$table = 'backend_layout';
		$where = 'deleted=0';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fields, $table, $where, '', '', '');

		$beLayouts = array();
		foreach($res as $ge) {
			$beLayouts[$ge['uid']] = $ge['title'];
		}

		return $beLayouts;
	}

	/**
	 * Returns an array with names of content columns for the given TemplaVoila DataStructure
	 *
	 * @param int $uidTvDs
	 * @return array
	 */
	public function getTvContentCols($uidTvDs) {
		$dsRecord = $this->getTvDatastructure($uidTvDs);
		$flexform = simplexml_load_string($dsRecord['dataprot']);
		$elements = $flexform->xpath("ROOT/el/*");

		$contentCols = array();
		$contentCols[''] = Tx_Extbase_Utility_Localization::translate('label_select', 'sf_tvtools');
		foreach ($elements as $element) {
			if ($element->tx_templavoila->eType == 'ce') {
				$contentCols[$element->getName()] = (string)$element->tx_templavoila->title;
			}
		}
		return $contentCols;
	}

	/**
	 * Returns an array with names of content columns for the given backend layout
	 *
	 * @param int $uidBeLayout
	 * @return array
	 */
	public function getBeLayoutContentCols($uidBeLayout) {
		$beLayoutRecord = $this->getBeLayout($uidBeLayout);
		$parser = t3lib_div::makeInstance('t3lib_TSparser');
		$parser->parse($beLayoutRecord['config']);
		$data = $parser->setup['backend_layout.'];

		$contentCols = array();
		$contentCols[''] = Tx_Extbase_Utility_Localization::translate('label_select', 'sf_tvtools');
		foreach($data['rows.'] as $row) {
			foreach($row['columns.'] as $column) {
				$contentCols[$column['colPos']] = $column['name'];
			}
		}
		return $contentCols;
	}

	/**
	 * Returns the TemplaVoila page template for the given page uid
	 *
	 * @param $pageUid
	 * @return array|bool mixed
	 */
	public function getTvPageTemplateRecord($pageUid) {
		$pageRecord = $this->getPageRecord($pageUid);
		return $this->sharedHelper->getTemplavoilaAPIObj()->getContentTree_fetchPageTemplateObject($pageRecord);
	}

	/**
	 * Returns the uid of the TemplaVoila page template for the given page uid
	 *
	 * @param $pageUid
	 * @return array|bool mixed
	 */
	public function getTvPageTemplateUid($pageUid) {
		$tvPageTemplateRecord = $this->getTvPageTemplateRecord($pageUid);
		return $tvPageTemplateRecord['uid'];
	}

	/**
	 * Returns the page record for the given page uid
	 *
	 * @param $uid
	 * @return array mixed
	 */
	public function getPageRecord($uid) {
		$fields = '*';
		$table = 'pages';
		$where = 'uid=' . (int)$uid;

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
		return $res;
	}

	/**
	 * Returns the DS record for the given DS uid
	 *
	 * @param $uid
	 * @return array mixed
	 */
	public function getTvDatastructure($uid) {
		$fields = '*';
		$table = 'tx_templavoila_datastructure';
		$where = 'uid=' . (int)$uid;

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
		return $res;
	}

	/**
	 * Returns the BE Layout record for the given BE Layout uid
	 *
	 * @param $uid
	 * @return array mixed
	 */
	public function getBeLayout($uid) {
		$fields = '*';
		$table = 'backend_layout';
		$where = 'uid=' . (int)$uid;

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
		return $res;
	}

	/**
	 * Returns the uid of the DS for the given template
	 *
	 * @param int $uidTemplate
	 * @return int
	 */
	public function getTvDsUidForTemplate($uidTemplate) {
		$fields = 'datastructure';
		$table = 'tx_templavoila_tmplobj';
		$where = 'uid=' . (int)$uidTemplate;

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
		return $res['datastructure'];
	}

	/**
	 * Returns an array of TV FlexForm content fields. The content elements are seperated by comma
	 *
	 * @param int $pageUid
	 * @return array
	 */
	public function getTvContentArray($pageUid) {
		$fields = 'tx_templavoila_flex';
		$table = 'pages';
		$where = 'uid=' . (int)$pageUid;

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow($fields, $table, $where, '', '', '');
		$flexform = simplexml_load_string($res['tx_templavoila_flex']);
		$elements = $flexform->xpath("data/sheet/language/*");

		$contentArray = array();
		foreach($elements as $element) {
			$contentArray[(string)$element->attributes()->index] = (string)$element->value;
		}
		return $contentArray;
	}

	/**
	 * Migrates all content elements for the page with the given pageUid to the selected column positions
	 *
	 * @param array $formdata
	 * @param int $pageUid
	 * @return int Number of Content elements updated
	 */
	public function migrateContentForPage($formdata, $pageUid) {
		$fieldMapping = $this->getFieldMappingArray($formdata);
		$tvContentArray = $this->getTvContentArray($pageUid);

		$count = 0;
		foreach ($tvContentArray as $key => $contentUidString) {
			if (array_key_exists($key, $fieldMapping) && $contentUidString != '') {
				$contentUids = explode(',', $contentUidString);
				foreach ($contentUids as $contentUid) {
					$this->updateContentElement($contentUid, $fieldMapping[$key]);
					$count++;
				}
			}
		}
		return $count;
	}

	/**
	 * Sets the backend layout uid for the page with the given uid if the value of the TV template matches
	 * the uid of the given uidTvTemplate
	 *
	 * @param int $pageUid
	 * @param int $UidTvTemplate
	 * @param int $uidBeLayout
	 * @return int Number of page templates updated
	 */
	public function updatePageTemplate($pageUid, $UidTvTemplate, $uidBeLayout) {
		$pageRecord = $this->getPageRecord($pageUid);
		$updateFields = array();
		$count = 0;
		if ($pageRecord['tx_templavoila_to'] > 0 && $pageRecord['tx_templavoila_to'] == $UidTvTemplate) {
			$updateFields['backend_layout'] = $uidBeLayout;
		}
		if ($pageRecord['tx_templavoila_next_to'] > 0 && $pageRecord['tx_templavoila_next_to'] == $UidTvTemplate) {
			$updateFields['backend_layout_next_level'] = $uidBeLayout;
		}
		if (count($updateFields) > 0) {
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery('pages', 'uid=' . intval($pageUid), $updateFields);
			$count++;
		}
		return $count;
	}

	/**
	 * Returns an array of field mappings, where the array key represents the TV field name and the value the
	 * BE layout column
	 *
	 * @param array $formdata
	 * @return array
	 */
	private function getFieldMappingArray($formdata) {
		$tvIndex = array();
		$beValue = array();
		foreach($formdata as $key => $data) {
			if (substr($key, 0, 7) == 'tv_col_') {
				$tvIndex[] = $data;
			}
			if (substr($key, 0, 7) == 'be_col_') {
				$beValue[] = $data;
			}
		}
		$fieldMapping = array();
		if (count($tvIndex) == count($beValue)) {
			for ($i=0; $i<=count($tvIndex); $i++) {
				if ($tvIndex[$i] != '' && $beValue[$i] != '') {
					$fieldMapping[$tvIndex[$i]] = $beValue[$i];
				}
			}
		}
		return $fieldMapping;
	}

	/**
	 * Sets the given colpos for the content element with the given uid
	 *
	 * @param int $uid
	 * @param int $newColPos
	 * @return void
	 */
	private function updateContentElement($uid, $newColPos) {
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tt_content', 'uid=' . intval($uid), array('colPos' => $newColPos));
	}

}

?>