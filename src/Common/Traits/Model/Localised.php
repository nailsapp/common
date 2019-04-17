<?php

namespace Nails\Common\Traits\Model;

use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Resource;
use Nails\Common\Service\Database;
use Nails\Common\Service\Locale;
use Nails\Factory;

/**
 * Trait Localised
 *
 * @package Nails\Common\Traits\Model
 */
trait Localised
{
    /**
     * The name of the column containing the language string
     *
     * @var string
     */
    protected static $sColumnLanguage = 'language';

    /**
     * The name of the column containing the region string
     *
     * @var string
     */
    protected static $sColumnRegion = 'region';

    /**
     * The suffix added to the localised table
     *
     * @var string
     */
    protected static $sLocalisedTableSuffix = '_localised';

    /**
     * The suffix added to the localised table alias
     *
     * @var string
     */
    protected static $sLocalisedTableAliasSuffix = 'l';

    // --------------------------------------------------------------------------

    /**
     * Overloads the getAll to add a Locale object to each resource
     *
     * @param int|null|array $iPage           The page number of the results, if null then no pagination; also accepts an $aData array
     * @param int|null       $iPerPage        How many items per page of paginated results
     * @param array          $aData           Any data to pass to getCountCommon()
     * @param bool           $bIncludeDeleted If non-destructive delete is enabled then this flag allows you to include deleted items
     *
     * @return Resource[]
     * @throws FactoryException
     */
    public function getAll($iPage = null, $iPerPage = null, array $aData = [], $bIncludeDeleted = false): array
    {
        $aResult = parent::getAll($iPage, $iPerPage, $aData, $bIncludeDeleted);
        $this->addLocaleToResources($aResult);
        return $aResult;
    }

    // --------------------------------------------------------------------------

    /**
     * Overloads the getCountCommon method to inject localisation query modifiers
     *
     * @param array $aData Any data to pass to parent::getCountCommon()
     *
     * @throws FactoryException
     * @throws ModelException
     */
    protected function getCountCommon(array $aData = [])
    {
        $this->injectLocalisationQuery($aData);
        parent::getCountCommon($aData);
    }

    // --------------------------------------------------------------------------

    /**
     * Injects localisation modifiers
     *
     * @param array $aData The data passed to getCountCommon()
     *
     * @throws FactoryException
     * @throws ModelException
     */
    protected function injectLocalisationQuery(array &$aData): void
    {
        /** @var Locale $oLocale */
        $oLocale = Factory::service('Locale');
        $sTable  = $this->getTableName();
        $sAlias  = $this->getTableAlias();

        /**
         * Restrict to a specific locale by passing in USE_LOCALE to the data array
         * Pass NO_LOCALISE_FILTER to the data array the developer can return items for all locales
         */

        if (!array_key_exists('select', $aData)) {
            $aData['select'] = [
                $sAlias . '.*',
            ];
        }

        if (array_key_exists('USE_LOCALE', $aData)) {
            if ($aData['USE_LOCALE'] instanceof \Nails\Common\Factory\Locale) {
                $oUserLocale = $aData['USE_LOCALE'];
            } else {
                list($sLanguage, $sRegion) = $oLocale::parseLocaleString($aData['USE_LOCALE']);
                $oUserLocale = $this->getLocale($sLanguage, $sRegion);
            }

            if (!array_key_exists('where', $aData)) {
                $aData['where'] = [];
            }

            $aData['where'][] = ['language', $oUserLocale->getLanguage()];
            $aData['where'][] = ['region', $oUserLocale->getRegion()];

        } elseif (!array_key_exists('NO_LOCALISE_FILTER', $aData)) {

            $oUserLocale      = $oLocale->get();
            $sUserLanguage    = $oUserLocale->getLanguage();
            $sUserRegion      = $oUserLocale->getRegion();
            $oDefaultLocale   = $oLocale->getDefautLocale();
            $sDefaultLanguage = $oDefaultLocale->getLanguage();
            $sDefaultRegion   = $oDefaultLocale->getRegion();

            $sQueryExact    = 'SELECT COUNT(*) FROM ' . $sTable . ' sub_1 WHERE sub_1.id = ' . $sAlias . '.id AND sub_1.' . static::$sColumnLanguage . ' = "' . $sUserLanguage . '" AND sub_1.' . static::$sColumnRegion . ' = "' . $sUserRegion . '"';
            $sQueryLanguage = 'SELECT COUNT(*) FROM ' . $sTable . ' sub_2 WHERE sub_2.id = ' . $sAlias . '.id AND sub_2.' . static::$sColumnLanguage . ' = "' . $sUserLanguage . '" AND sub_2.' . static::$sColumnRegion . ' != "' . $sUserRegion . '"';

            $aConditionals = [
                '((' . $sQueryExact . ') = 1 AND ' . static::$sColumnLanguage . ' = "' . $sUserLanguage . '" AND ' . static::$sColumnRegion . ' = "' . $sUserRegion . '")',
                '((' . $sQueryExact . ') = 0 AND ' . static::$sColumnLanguage . ' = "' . $sUserLanguage . '")',
                '((' . $sQueryExact . ') = 0 AND (' . $sQueryLanguage . ') = 0 AND ' . static::$sColumnLanguage . ' = "' . $sDefaultLanguage . '" AND ' . static::$sColumnRegion . ' = "' . $sDefaultRegion . '")',
            ];

            if (!array_key_exists('where', $aData)) {
                $aData['where'] = [];
            }

            $aData['where'][] = implode(' OR ', $aConditionals);
        }

        //  Ensure each row knows about the other items available
        $sQuery = 'SELECT GROUP_CONCAT(CONCAT(`others`.`language`, \'_\', `others`.`region`)) FROM ' . $sTable . ' `others` WHERE `others`.`id` = `' . $sAlias . '`.`id`';
        if (!$this->isDestructiveDelete()) {
            $sQuery .= ' AND `others`.`' . $this->getColumn('deleted') . '` = 0';
        }
        $aData['select'][] = '(' . $sQuery . ') available_locales';
    }

    // --------------------------------------------------------------------------

    /**
     * Adds a locale object to an array of Resources
     *
     * @param array $aResources The array of Resources
     *
     * @throws FactoryException
     */
    protected function addLocaleToResources(array $aResources): void
    {
        foreach ($aResources as $oResource) {
            $this->addLocaleToResource($oResource);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Adds a Locale object to a Resource, and removes the language and region properties
     *
     * @param Resource $oResource The resource to modify
     *
     * @throws FactoryException
     */
    protected function addLocaleToResource(Resource $oResource): void
    {
        /** @var Locale $oLocale */
        $oLocale = Factory::service('Locale');

        //  Add the locale for _this_ item
        $oResource->locale = $this->getLocale(
            $oResource->{static::$sColumnLanguage},
            $oResource->{static::$sColumnRegion}
        );

        //  Set the locales for all _available_ items
        $oResource->available_locales = array_map(function ($sLocale) use ($oLocale) {
            list($sLanguage, $sRegion) = $oLocale::parseLocaleString($sLocale);
            return $this->getLocale($sLanguage, $sRegion);
        }, explode(',', $oResource->available_locales));

        //  Specify which locales are missing
        $oResource->missing_locales = array_diff(
            $oLocale->getSupportedLocales(),
            $oResource->available_locales
        );

        //  Remove internal fields
        unset($oResource->{static::$sColumnLanguage});
        unset($oResource->{static::$sColumnRegion});
    }

    // --------------------------------------------------------------------------

    /**
     * Generate a Locale object for a language/region
     *
     * @param string $sLanguage The language to set
     * @param string $sRegion   The region to set
     *
     * @return \Nails\Common\Factory\Locale
     * @throws FactoryException
     */
    private function getLocale(string $sLanguage, string $sRegion): \Nails\Common\Factory\Locale
    {
        return Factory::factory('Locale')
            ->setLanguage(Factory::factory('LocaleLanguage', null, $sLanguage))
            ->setRegion(Factory::factory('LocaleRegion', null, $sRegion))
            ->setScript(Factory::factory('LocaleScript'));
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the localised table name
     *
     * @param bool $bIncludeAlias Whether to include the table alias or not
     *
     * @return string
     * @throws ModelException
     */
    public function getTableName($bIncludeAlias = false): string
    {
        $sTable = parent::getTableName() . static::$sLocalisedTableSuffix;
        return $bIncludeAlias ? trim($sTable . ' as `' . $this->getTableAlias() . '`') : $sTable;
    }

    // --------------------------------------------------------------------------

    /**
     * Create a new localised item
     *
     * @param array                             $aData         The data array
     * @param bool                              $bReturnObject Whether to return the item's ID or the object on success
     * @param \Nails\Common\Factory\Locale|null $oLocale       The locale to create the item in
     *
     * @return null|int|Resource
     * @throws FactoryException
     * @throws ModelException
     */
    public function create(array $aData = [], $bReturnObject = false, \Nails\Common\Factory\Locale $oLocale = null)
    {
        /** @var Database $oDb */
        $oDb = Factory::service('Database');

        if (empty($oLocale)) {
            throw new ModelException(
                'A locale must be defined when creating a localised item'
            );
        }

        $aData['language'] = $oLocale->getLanguage();
        $aData['region']   = $oLocale->getRegion();

        if (empty($aData['id'])) {
            $oDb->set('id', null);
            $oDb->insert(parent::getTableName());
            $aData['id'] = $oDb->insert_id();
            if (empty($aData['id'])) {
                throw new ModelException(
                    'Failed to generate parent item for localised object'
                );
            }
            $bCreatedItem = true;
        }

        if (!$this->isDestructiveDelete()) {
            /**
             * This is to prevent primary key conflicts if a previously deleted item still exists in the table
             */
            $this->destroy($aData['id'], $oLocale);
        }

        $iItemId = parent::create($aData, false);

        if (empty($iItemId)) {
            if (!empty($bCreatedItem)) {
                $oDb->where('id', $aData['id']);
                $oDb->delete(parent::getTableName());
            }
            return null;
        } elseif (!$bReturnObject) {
            return $iItemId;
        }

        return $this->getById($iItemId, ['USE_LOCALE' => $oLocale]);
    }

    // --------------------------------------------------------------------------

    /**
     * Updates an existing object
     *
     * @param int                               $iId     The ID of the object to update
     * @param array                             $aData   The data to update the object with
     * @param \Nails\Common\Factory\Locale|null $oLocale The locale of the object being updated
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     */
    public function update($iId, array $aData = [], \Nails\Common\Factory\Locale $oLocale = null): bool
    {
        if (empty($oLocale)) {
            throw new ModelException(
                'A locale must be defined when updating a localised item'
            );
        }

        $oDb = Factory::service('Database');
        $oDb->where('language', $oLocale->getLanguage());
        $oDb->where('region', $oLocale->getRegion());

        return parent::update($iId, $aData);
    }

    // --------------------------------------------------------------------------

    /**
     * Marks an object as deleted
     *
     * @param int                               $iId     The ID of the object to mark as deleted
     * @param \Nails\Common\Factory\Locale|null $oLocale The locale of the object being deleted
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     */
    public function delete($iId, \Nails\Common\Factory\Locale $oLocale = null): bool
    {
        if (empty($oLocale)) {
            throw new ModelException(
                'A locale must be defined when deleting a localised item'
            );
        }

        /**
         * An item can be deleted if any of the following are true:
         * - It is the only item
         * - It is not the default locale
         */

        /** @var Locale $oLocale */
        $oLocaleService = Factory::service('Locale');
        $oItem          = $this->getById($iId, ['USE_LOCALE' => $oLocale]);

        if (count($oItem->available_locales) === 1 || $oLocaleService->getDefautLocale() !== $oLocale) {

            if ($this->isDestructiveDelete()) {
                $bResult = $this->destroy($iId, $oLocale);
            } else {
                $bResult = $this->update(
                    $iId,
                    [$this->tableDeletedColumn => true],
                    $oLocale
                );
            }

            if ($bResult) {
                $this->triggerEvent(static::EVENT_DELETED, [$iId, $oLocale]);
                return true;
            }

            return false;

        } elseif (count($oItem->available_locales) > 1 && $oLocaleService->getDefautLocale() === $oLocale) {
            throw new ModelException(
                'Item cannot be deleted as it is the default item and other items still exist.'
            );
        } else {
            throw new ModelException(
                'Item cannot be deleted'
            );
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Permanently deletes an object
     *
     * @param int                               $iId     The ID  of the object to destroy
     * @param \Nails\Common\Factory\Locale|null $oLocale The locale of the item being destroyed
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     */
    public function destroy($iId, \Nails\Common\Factory\Locale $oLocale = null): bool
    {
        if (empty($oLocale)) {
            throw new ModelException(
                'A locale must be defined when deleting a localised item'
            );
        }

        $oDb = Factory::service('Database');
        $oDb->where('language', $oLocale->getLanguage());
        $oDb->where('region', $oLocale->getRegion());

        return parent::destroy($iId);
    }

    // --------------------------------------------------------------------------

    /**
     * Unmarks an object as deleted
     *
     * @param int                               $iId     The ID of the object to restore
     * @param \Nails\Common\Factory\Locale|null $oLocale The locale of the item being restored
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     */
    public function restore($iId, \Nails\Common\Factory\Locale $oLocale = null): bool
    {
        if (empty($oLocale)) {
            throw new ModelException(
                'A locale must be defined when restoring a localised item'
            );
        }

        if ($this->isDestructiveDelete()) {
            return null;
        } elseif ($this->update($iId, [$this->tableDeletedColumn => false], $oLocale)) {
            $this->triggerEvent(static::EVENT_RESTORED, [$iId]);
            return true;
        }

        return false;
    }
}
