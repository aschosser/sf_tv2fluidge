<?php
namespace Hansen\SfTv2fluidge\Service;
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
 * Helper class for handling unreferenced elements
 */
class ReferenceElementHelper implements \TYPO3\CMS\Core\SingletonInterface
{

    /**
     * @var \Hansen\SfTv2fluidge\Service\SharedHelper
     * @inject
     */
    protected $sharedHelper;

    /**
     * @var \Hansen\SfTv2fluidge\Service\LogHelper
     * @inject
     */
    protected $logHelper;

    /**
     * @var \TYPO3\CMS\Core\Database\ReferenceIndex
     * @inject
     */
    protected $refIndex;

    /**
     * @var bool
     */
    protected $useParentUidForTranslations = false;

    /**
     * @var bool
     */
    protected $useAllLangIfDefaultLangIsReferenced = false;

    /**
     * Array to count the amount of conversions for a content element on the current page
     * Structure: $pid => $count
     *
     * @var array
     */
    protected $conversionCount = array();

    /**
     * @param array $formdata
     */
    public function initFormData($formdata)
    {
        $this->useParentUidForTranslations = (intval($formdata['useparentuidfortranslations']) === 1);
        $this->useAllLangIfDefaultLangIsReferenced = (intval($formdata['usealllangifdefaultlangisreferenced']) === 1);
    }

    /**
     * Converts all reference elements to 'insert records' elements with the recursion level set in the extension settings
     *
     * @return int Number of records deleted
     */
    public function convertReferenceElements()
    {
        $this->logHelper->logMessage('===== ' . __CLASS__ . ' - ' . __FUNCTION__ . ' =====');
        $this->logHelper->logMessage('Starting ...');

        $GLOBALS['TCA']['tt_content']['ctrl']['hideAtCopy'] = 0;
        $GLOBALS['TCA']['tt_content']['ctrl']['prependAtCopy'] = 0;

        $pids = $this->sharedHelper->getPageIds();
        $numRecords = 0;
        foreach ($pids as $pid) {
            // Reset conversionCount
            $this->conversionCount = array();
            $tvContentArray = $this->sharedHelper->getTvContentArrayByLanguageAndFieldForPage($pid);
            $numRecords += $this->convertTvContentArrayToReferenceElements($tvContentArray, $pid);
        }

        $this->logHelper->logMessage('===== ' . __CLASS__ . ' - ' . __FUNCTION__ . ' =====');
        $this->logHelper->logMessage('Finished. Got ' . $numRecords . ' references.');

        return $numRecords;
    }

    /**
     * Converts an array of content elements to references, if they are references
     * also handles references inside fce
     *
     * @param array $tvContentArray
     * @param int $pid
     * @param int $fceUid
     * @return int
     */
    protected function convertTvContentArrayToReferenceElements($tvContentArray, $pid, $fceUid = 0)
    {
        $numRecords = 0;
        $pid = (int)$pid;
        $fceUid = (int)$fceUid;

        // Respect language
        foreach ($tvContentArray as $lang => $fields) {
            // Cycle through each field
            foreach ($fields as $field => $contentUidString) {
                $contentUids = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $contentUidString);
                $position = 1;
                foreach ($contentUids as $contentUid) {
                    $contentUid = (int)$contentUid;
                    $contentElement = $this->sharedHelper->getContentElement($contentUid);
                    $contentElementPid = (int)$contentElement['pid'];
                    if ($this->sharedHelper->isContentElementAvailable($contentUid)) {
                        // If content element is included in conversionCount array, we have several references to the
                        // content element on the same page
                        if (isset($this->conversionCount[$contentUid])) {
                            $this->conversionCount[$contentUid] = $this->conversionCount[$contentUid] + 1;
                            $numRecords += $this->convertReferencesToShortcut($contentUid, $contentElementPid, $pid, $field, $position, $fceUid, $lang, true);
                        } else {
                            $this->conversionCount[$contentUid] = 1;
                            $numRecords += $this->convertReferencesToShortcut($contentUid, $contentElementPid, $pid, $field, $position, $fceUid, $lang);
                        }
                        ++$position;
                    }
                }
            }
        }
        return $numRecords;
    }

    /**
     * Converts reference content elements, either current content element or sub content elements (FCE)
     * including translations to a insert record element
     *
     * @param int $contentUid
     * @param int $contentElementPid
     * @param int $pid
     * @param string $field
     * @param int $position
     * @param int $fceUid
     * @param string $lang
     * @param bool $force Force conversion using convertReferenceToShortcut()
     * @return int
     */
    protected function convertReferencesToShortcut($contentUid, $contentElementPid, $pid, $field, $position, $fceUid = 0, $lang = 'lDEF', $force = false)
    {
        $numRecords = 0;
        $contentElementPid = (int)$contentElementPid;
        $pid = (int)$pid;
        $fceUid = (int)$fceUid;
        // Only create a shortcut, when content element is not on given PID or if conversion is forced
        if ($contentElementPid !== $pid || $force) {
            $numRecords += $this->convertReferenceToShortcut($contentUid, $pid, $field, $position, $fceUid, $lang);
        } else {
            $numRecords += $this->convertReferencesInsideFceToShortcut($contentUid, $pid);
        }
        return $numRecords;
    }

    /**
     * Converts a reference content element - either current content element or sub content elements (FCE)
     * including translations to a insert record element
     *
     * @param int $contentUid
     * @param int $pid
     * @param string $field
     * @param int $position
     * @param int $fceUid
     * @param string $lang
     * @return int
     */
    protected function convertReferenceToShortcut($contentUid, $pid, $field, $position, $fceUid = 0, $lang = 'lDEF')
    {
        $numRecords = 0;
        $newContentUid = null;
        if ($fceUid > 0) {
            $newContentUid = $this->convertFceToLocalCopy($fceUid, $field, $position, $lang);
        } else {
            $newContentUid = $this->convertPageCeToLocalCopy($pid, $field, $position, $lang);
        }

        $newContentUid = (int)$newContentUid;
        if ($newContentUid > 0) {
            $this->convertToShortcut($newContentUid, $contentUid);

            if (!$this->convertShortcutToAllLangShortCut($newContentUid, $contentUid)) {
                $this->convertTranslationsOfShortcut($newContentUid, $contentUid);
            }

            $this->refIndex->updateRefIndexTable('tt_content', $newContentUid);
            ++$numRecords;
        }
        return $numRecords;
    }

    /**
     * Converts a shortcut element to an all language shortcut
     *
     * @param $contentUid
     * @param $targetUid
     * @return bool
     */
    protected function convertShortcutToAllLangShortCut($contentUid, $targetUid)
    {
        $contentUid = (int)$contentUid;
        $targetUid = (int)$targetUid;
        $convertedToAllLang = false;
        if (($contentUid > 0) && ($targetUid > 0)) {
            $contentElement = $this->sharedHelper->getContentElement($targetUid);

            if ($this->useAllLangIfDefaultLangIsReferenced && !empty($contentElement)) {
                $ceSysLanguageUid = (int)$contentElement['sys_language_uid'];
                $languageParent = (int)$contentElement['l18n_parent'];
                if ((($ceSysLanguageUid === -1) || ($ceSysLanguageUid === 0))
                    && ($languageParent === 0)) {
                    $this->convertToAllLang($contentUid);
                    $this->deleteTranslations($contentUid);
                    $this->sharedHelper->fixContentElementLocalizationDiffSources($contentUid);
                    $convertedToAllLang = true;
                }
            }
        }
        return $convertedToAllLang;
    }

    /**
     * converts a references inside FCE to insert record elements
     *
     * @param int $contentUid
     * @param int $pid
     * @return int
     */
    protected function convertReferencesInsideFceToShortcut($contentUid, $pid)
    {
        $numRecords = 0;
        $fceContentElements = $this->sharedHelper->getTvContentArrayForContent($contentUid);
        if (count($fceContentElements) > 0) {
            $numRecords += $this->convertTvContentArrayToReferenceElements($fceContentElements, $pid, $contentUid);
        }
        return $numRecords;
    }

    /**
     * Converts page content element reference to local copy
     *
     * @param integer $pageUid
     * @param string $field
     * @param integer $position
     * @param string $lang
     * @return integer
     */
    protected function convertPageCeToLocalCopy($pageUid, $field, $position, $lang)
    {
        $flexformPointerString = 'pages:' . (int)$pageUid . ':sDEF:' . $lang . ':' . $field . ':vDEF:' . (int)$position;
        return $this->convertFlexformPointerStringToLocalCopy($flexformPointerString);
    }

    /**
     * Converts fce reference to local copy
     *
     * @param integer $contentUid
     * @param string $field
     * @param integer $position
     * @param string $lang
     * @return integer
     */
    protected function convertFceToLocalCopy($contentUid, $field, $position, $lang)
    {
        $flexformPointerString = 'tt_content:' . (int)$contentUid . ':sDEF:' . $lang . ':' . $field . ':vDEF:' . (int)$position;
        return $this->convertFlexformPointerStringToLocalCopy($flexformPointerString);
    }

    /**
     * Converts flexform pointer string to local copy
     *
     * @param string $flexformPointerString
     * @return mixed
     */
    protected function convertFlexformPointerStringToLocalCopy($flexformPointerString)
    {
        $sourcePointer = $this->sharedHelper->getTemplavoilaAPIObj()->
            flexform_getPointerFromString($flexformPointerString);

        $contentUid = $this->sharedHelper->getTemplavoilaAPIObj()->
            copyElement($sourcePointer, $sourcePointer);
        $this->sharedHelper->getTemplavoilaAPIObj()->
            unlinkElement($sourcePointer);

        return $contentUid;
    }

    /**
     * Converts element to shortcut
     *
     * @param integer $contentUid
     * @param integer $targetUid
     * @return void
     */
    protected function convertToShortcut($contentUid, $targetUid)
    {
        $targetUid = (int)$targetUid;
        $contentUid = (int)$contentUid;
        $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
            'tt_content',
            'uid = ' . $contentUid,
            array(
                'CType'   => 'shortcut',
                'records' => 'tt_content_' . $targetUid,
            )
        );
        $this->logHelper->logMessage('===== ' . __CLASS__ . ' - ' . __FUNCTION__ . ' =====');
        $this->logHelper->logMessage('Update ' . $contentUid . ' as shortcut to ' . $targetUid);
    }

    /**
     * Converts content element to all language content element
     *
     * @param int $contentUid
     */
    protected function convertToAllLang($contentUid)
    {
        $contentUid = (int)$contentUid;
        $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
            'tt_content',
            'uid = ' . $contentUid,
            array(
                'sys_language_uid'   => -1,
            )
        );
    }

    /**
     * Deletes translation of content element
     *
     * @param int $contentUid
     */
    protected function deleteTranslations($contentUid)
    {
        $contentUid = (int)$contentUid;
        $GLOBALS['TYPO3_DB']->exec_DELETEquery(
            'tt_content',
            '(l18n_parent =' . $contentUid . ')' .
            \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('tt_content')
        );
    }

    /**
     * Converts translated records to shortcut
     *
     * @param integer $contentUid
     * @param integer $targetUid
     * @return void
     */
    protected function convertTranslationsOfShortcut($contentUid, $targetUid)
    {
        if ($this->useParentUidForTranslations) {
            $this->convertTranslationsToShortCutUsingParentUid($contentUid, $targetUid);
        } else {
            $this->convertTranslationsToShortCutUsingTranslationUid($contentUid, $targetUid);
        }

        $this->sharedHelper->fixContentElementLocalizationDiffSources($contentUid);
    }

    /**
     * Converts translated records to shortcut using uid of parent content element as record reference
     *
     * @param integer $contentUid
     * @param integer $targetUid
     * @return void
     */
    protected function convertTranslationsToShortCutUsingParentUid($contentUid, $targetUid)
    {
        $contentUid = (int)$contentUid;
        $targetUid = (int)$targetUid;
        $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
            'tt_content',
            '(l18n_parent =' . $contentUid . ')' .
            \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('tt_content'),
            array(
                'CType'   => 'shortcut',
                'records' => 'tt_content_' . $targetUid,
            )
        );
    }

    /**
     * Converts translated records to shortcut using uid of translation content element as record reference
     *
     * @param integer $contentUid
     * @param integer $targetUid
     * @return void
     */
    protected function convertTranslationsToShortCutUsingTranslationUid($contentUid, $targetUid)
    {
        $contentUid = (int)$contentUid;
        $targetUid = (int)$targetUid;
        $translations = $this->sharedHelper->getTranslationsForContentElement($targetUid);
        if (!empty($translations)) {
            foreach ($translations as $translation) {
                $translationTargetUid = (int)$translation['uid'];
                $translationTargetSysLanguageUid = (int)$translation['sys_language_uid'];
                if (($translationTargetUid > 0) && ($translationTargetSysLanguageUid > 0)) {
                    $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                        'tt_content',
                        '(l18n_parent = ' . $contentUid . ')' .
                        ' AND (sys_language_uid = '  . $translationTargetSysLanguageUid . ')' .
                        \TYPO3\CMS\Backend\Utility\BackendUtility::deleteClause('tt_content'),
                        array(
                            'CType'   => 'shortcut',
                            'records' => 'tt_content_' . $translationTargetUid,
                        )
                    );
                }
            }

            $this->updateRefIndexTranslations($contentUid);
        }
    }

    /**
     * Updates sys_refindex for translation content elements
     *
     * @param $contentUid
     */
    protected function updateRefIndexTranslations($contentUid)
    {
        $updateRefIndexTranslations = $this->sharedHelper->getTranslationsForContentElement($contentUid);
        if (!empty($updateRefIndexTranslations)) {
            foreach ($updateRefIndexTranslations as $updateRefIndexTranslation) {
                $updateRefIndexTranslationUid = (int)$updateRefIndexTranslation['uid'];
                if ($updateRefIndexTranslationUid > 0) {
                    $this->refIndex->updateRefIndexTable('tt_content', $updateRefIndexTranslationUid);
                }
            }
        }
    }
}
