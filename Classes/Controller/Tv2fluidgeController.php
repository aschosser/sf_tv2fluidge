<?php
namespace Hansen\SfTv2fluidge\Controller;
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Torben Hansen <derhansen@gmail.com>, Skyfillers GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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
 * TV Tv2fluidge Backend Controller
 */
class Tv2fluidgeController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{

    /**
     * UnreferencedElementHelper
     *
     * @var \Hansen\SfTv2fluidge\Service\UnreferencedElementHelper
     * @inject
     */
    protected $unreferencedElementHelper;

    /**
     * ReferenceElementHelper
     *
     * @var \Hansen\SfTv2fluidge\Service\ReferenceElementHelper
     * @inject
     */
    protected $referenceElementHelper;

    /**
     * MigrateFceHelper
     *
     * @var \Hansen\SfTv2fluidge\Service\MigrateFceHelper
     * @inject
     */
    protected $migrateFceHelper;

    /**
     * MigrateContentHelper
     *
     * @var \Hansen\SfTv2fluidge\Service\MigrateContentHelper
     * @inject
     */
    protected $migrateContentHelper;

    /**
     * @var \Hansen\SfTv2fluidge\Service\FixSortingHelper
     * @inject
     */
    protected $fixSortingHelper;

    /**
     * @var \Hansen\SfTv2fluidge\Service\SharedHelper
     * @inject
     */
    protected $sharedHelper;

    /**
     * @var \Hansen\SfTv2fluidge\Service\ConvertMultilangContentHelper
     * @inject
     */
    protected $convertMultilangContentHelper;

    /**
     * Default index action for module
     *
     * @return void
     */
    public function indexAction()
    {
        $this->view->assignMultiple(
            array(
                'rootPid' => $this->sharedHelper->getConversionRootPid(),
                'includeNonRootPages' => $this->sharedHelper->getIncludeNonRootPagesIsEnabled(),
                'pagesDepthLimit' => $this->sharedHelper->getPagesDepthLimit()
            )
        );
    }

    /**
     * Index action for unreferenced Elements module
     *
     * @return void
     */
    public function IndexDeleteUnreferencedElementsAction()
    {
    }

    /**
     * Sets all unreferenced Elements to deleted
     *
     * @param array $formdata
     * @return void
     */
    public function deleteUnreferencedElementsAction($formdata = null)
    {
        $this->sharedHelper->setUnlimitedTimeout();
        $markAsNegativeColPos = false;
        if (intval($formdata['markasnegativecolpos']) === 1) {
            $markAsNegativeColPos = true;
        }
        $ignoreshortcutpages = false;
        if (intval($formdata['ignoreshortcutpages']) === 1) {
            $ignoreshortcutpages = true;
        }
        $ignoresysfolders = false;
        if (intval($formdata['ignoresysfolders']) === 1) {
            $ignoresysfolders = true;
        }
        $numRecords = $this->unreferencedElementHelper->markDeletedUnreferencedElementsRecords(
            $markAsNegativeColPos,
            $ignoreshortcutpages,
            $ignoresysfolders
        );
        $this->view->assign('numRecords', $numRecords);
    }

    /**
     * Index action for migrate reference elements
     *
     * @return void
     */
    public function indexConvertReferenceElementsAction()
    {
    }

    /**
     * Migrates all reference elements to 'insert records' elements
     *
     * @param array $formdata
     * @return void
     */
    public function convertReferenceElementsAction($formdata = null)
    {
        $this->sharedHelper->setUnlimitedTimeout();

        $this->referenceElementHelper->initFormData($formdata);
        $numRecords = $this->referenceElementHelper->convertReferenceElements();
        $this->view->assign('numRecords', $numRecords);
    }

    /**
     * Index action for migrateFce
     *
     * @param array $formdata
     * @return void
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function indexMigrateFceAction($formdata = null)
    {
        if ($this->sharedHelper->getTemplavoilaStaticDsIsEnabled()) {
            $allFce = $this->migrateFceHelper->getAllFileFce();
        } else {
            $allFce = $this->migrateFceHelper->getAllDbFce();
        }
        $allGe = $this->migrateFceHelper->getAllGe();

        if (isset($formdata['fce'])) {
            $uidFce = intval($formdata['fce']);
        } else {
            $uidFce = current(array_keys($allFce));
        }

        if (isset($formdata['ge'])) {
            $geKey = $formdata['ge'];
        } else {
            $geKey = current(array_keys($allGe));
        }

        // Fetch content columns from FCE and GE depending on selection (first entry if empty)
        if ($uidFce > 0) {
            $fceContentCols = $this->sharedHelper->getTvContentCols($uidFce);
        } else {
            $fceContentCols = null;
        }

        if ($this->sharedHelper->canBeInterpretedAsInteger($geKey)) {
            $geKey = (int)$geKey;
            if ($geKey <= 0) {
                $geKey = 0;
            }
        }

        if (!empty($geKey)) {
            $geContentCols = $this->sharedHelper->getGeContentCols($geKey);
        } else {
            $geContentCols = null;
        }

        $this->view->assign('fceContentCols', $fceContentCols);
        $this->view->assign('geContentCols', $geContentCols);
        $this->view->assign('allFce', $allFce);
        $this->view->assign('allGe', $allGe);
        $this->view->assign('formdata', $formdata);

        // Redirect to migrateContentAction when submit button pressed
        if (isset($formdata['startAction'])) {
            $this->redirect(
                'migrateFce',
                null,
                null,
                array('formdata' => $formdata)
            );
        }
    }

    /**
     * Migrates content from FCE to Grid Element
     *
     * @param array $formdata
     * @return void
     */
    public function migrateFceAction($formdata)
    {
        $this->sharedHelper->setUnlimitedTimeout();

        $fce = $formdata['fce'];
        $ge = $formdata['ge'];
        if ($this->sharedHelper->canBeInterpretedAsInteger($ge)) {
            $ge = (int)$ge;
            if ($ge <= 0) {
                $ge = 0;
            }
        }

        $fcesConverted = 0;
        $contentElementsUpdated = 0;

        if ($fce > 0 && !empty($ge)) {
            $pageUids = $this->sharedHelper->getPageIds();
            $contentElements = $this->migrateFceHelper->getContentElementsByFce($fce, $pageUids);
            foreach ($contentElements as $contentElement) {
                $fcesConverted++;
                $this->migrateFceHelper->migrateFceFlexformContentToGe($contentElement, $ge);

                // Migrate content to GridElement columns (if available)
                $contentElementsUpdated += $this->migrateFceHelper->migrateContentElementsForFce(
                    $contentElement,
                    $formdata
                );
            }
            if ($formdata['markdeleted']) {
                $this->migrateFceHelper->markFceDeleted($fce);
            }
        }

        $this->view->assign('contentElementsUpdated', $contentElementsUpdated);
        $this->view->assign('fcesConverted', $fcesConverted);
    }

    /**
     * Index action for migrate content
     *
     * @param array $formdata
     * @return void
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function indexMigrateContentAction($formdata = null)
    {
        if ($this->sharedHelper->getTemplavoilaStaticDsIsEnabled()) {
            $tvtemplates = $this->migrateContentHelper->getAllFileTvTemplates();
        } else {
            $tvtemplates = $this->migrateContentHelper->getAllDbTvTemplates();
        }
        $beLayouts = $this->migrateContentHelper->getAllBeLayouts();

        if (isset($formdata['tvtemplate'])) {
            $uidTvTemplate = intval($formdata['tvtemplate']);
        } else {
            $uidTvTemplate = current(array_keys($tvtemplates));
        }

        if (isset($formdata['belayout'])) {
            $uidBeLayout = $formdata['belayout'];
        } else {
            $uidBeLayout = current(array_keys($beLayouts));
        }

        if (!isset($formdata['flexformfieldprefix'])) {
            $formdata['flexformfieldprefix'] = 'tx_';
        }

        if (!isset($formdata['convertflexformoption'])) {
            $formdata['convertflexformoption'] = 'merge';
        }

        // Fetch content columns from TV and BE layouts depending on selection (first entry if empty)
        $tvContentCols = $this->sharedHelper->getTvContentCols($uidTvTemplate);
        $beContentCols = $this->sharedHelper->getBeLayoutContentCols($uidBeLayout);

        $this->view->assign('tvContentCols', $tvContentCols);
        $this->view->assign('beContentCols', $beContentCols);
        $this->view->assign('tvtemplates', $tvtemplates);
        $this->view->assign('belayouts', $beLayouts);
        $this->view->assign('formdata', $formdata);

        // Redirect to migrateContentAction when submit button pressed
        if (isset($formdata['startAction'])) {
            $this->redirect(
                'migrateContent',
                null,
                null,
                array('formdata' => $formdata)
            );
        }
    }

    /**
     * Does the content migration recursive for all pages
     *
     * @param array $formdata
     * @return void
     */
    public function migrateContentAction($formdata)
    {
        $this->sharedHelper->setUnlimitedTimeout();

        $uidTvTemplate = (int)$formdata['tvtemplate'];
        $uidBeLayout = $formdata['belayout'];

        $contentElementsUpdated = 0;
        $pageTemplatesUpdated = 0;

        if ($uidTvTemplate > 0 && $uidBeLayout) {
            $pageUids = $this->sharedHelper->getPageIds();

            foreach ($pageUids as $pageUid) {
                if ($this->sharedHelper->getTvPageTemplateUid($pageUid) == $uidTvTemplate) {
                    $contentElementsUpdated += $this->migrateContentHelper->migrateContentForPage($formdata, $pageUid);
                    $this->migrateContentHelper->migrateTvFlexformForPage($formdata, $pageUid);
                }

                // Update page template (must be called for every page, since to and next_to must be checked
                $pageTemplatesUpdated += $this->migrateContentHelper->updatePageTemplate(
                    $pageUid,
                    $uidTvTemplate,
                    $uidBeLayout
                );
            }

            if ($formdata['markdeleted']) {
                $this->migrateContentHelper->markTvTemplateDeleted($uidTvTemplate);
            }
        }

        $this->view->assign('contentElementsUpdated', $contentElementsUpdated);
        $this->view->assign('pageTemplatesUpdated', $pageTemplatesUpdated);
    }

    /**
     * Index action for convert multilingual content
     *
     * @return void
     */
    public function indexConvertMultilangContentAction()
    {
    }

    /**
     * Does the content conversion for all GridElements on all pages
     *
     * @param array $formdata
     * @return void
     */
    public function convertMultilangContentAction($formdata = null)
    {
        $this->sharedHelper->setUnlimitedTimeout();

        $pageUids = $this->sharedHelper->getPageIds();

        $numGEs = 0;
        $numCEs = 0;

        $this->convertMultilangContentHelper->initFormData($formdata);

        foreach ($pageUids as $pageUid) {
            $numGEs += $this->convertMultilangContentHelper->cloneLangAllGEs($pageUid);
            $numCEs += $this->convertMultilangContentHelper->rearrangeContentElementsForGridelementsOnPage($pageUid);
        }

        $this->view->assign('numGEs', $numGEs);
        $this->view->assign('numCEs', $numCEs);
    }

    /**
     * Index action for fix sorting
     *
     * @param array $formdata
     * @return void
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function indexFixSortingAction($formdata = null)
    {
        $cancel = false;

        if ($formdata['fixOptions'] == 'singlePage' && $formdata['pageUid'] == '' && isset($formdata['startAction'])) {
            $cancel = true;
            $this->view->assign('pageUidMissing', true);
        }

        $this->view->assign('formdata', $formdata);

        // Redirect to fixSortingAction when submit button pressed
        if (isset($formdata['startAction']) && $cancel == false) {
            $this->redirect(
                'fixSorting',
                null,
                null,
                array('formdata' => $formdata)
            );
        }
    }

    /**
     * Action for fix sorting
     *
     * @param array $formdata
     * @return void
     */
    public function fixSortingAction($formdata)
    {
        $this->sharedHelper->setUnlimitedTimeout();

        $numUpdated = 0;
        if ($formdata['fixOptions'] == 'singlePage') {
            $numUpdated = $this->fixSortingHelper->fixSortingForPage($formdata['pageUid']);
        } else {
            $pageUids = $this->sharedHelper->getPageIds();
            foreach ($pageUids as $pageUid) {
                $numUpdated += $this->fixSortingHelper->fixSortingForPage($pageUid);
            }
        }
        $this->view->assign('numUpdated', $numUpdated);
    }
}
