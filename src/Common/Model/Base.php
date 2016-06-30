<?php

/**
 * This class brings about uniformity to Nails models.
 *
 * @package     Nails
 * @subpackage  common
 * @category    model
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Common\Model;

use Nails\Factory;
use Nails\Common\Exception\ModelException;
use \Nails\Common\Traits\ErrorHandling;
use \Nails\Common\Traits\Caching;
use \Nails\Common\Traits\GetCountCommon;

class Base
{
    use ErrorHandling;
    use Caching;
    use GetCountCommon;

    // --------------------------------------------------------------------------

    //  Common data
    protected $data;
    protected $user;
    protected $user_model;

    //  Data/Table structure
    protected $table;
    protected $tablePrefix;

    //  Column names
    protected $tableIdColumn;
    protected $tableSlugColumn;
    protected $tableLabelColumn;
    protected $tableCreatedColumn;
    protected $tableCreatedByColumn;
    protected $tableModifiedColumn;
    protected $tableModifiedByColumn;
    protected $tableDeletedColumn;
    protected $searchableFields;

    //  Model options
    protected $tableAutoSetTimestamps;
    protected $tableAutoSetSlugs;

    //  Expandable fields
    protected $aExpandableFields;

    /**
     * Expandable objects of type EXPANDABLE_TYPE_MANY are a 1 to many relationship
     * where a property of the child object is the ID of the parent object.
     */
    const EXPANDABLE_TYPE_MANY   = 0;

    /**
     * Expandable objects of type EXPANDABLE_TYPE_SINGLE are a 1 to 1 relationship
     * where a property of the parent object is the ID of the child object.
     */
    const EXPANDABLE_TYPE_SINGLE = 1;

    /**
     * Magic trigger for expanding all expandable objects
     */
    const EXPAND_ALL = 'ALL';

    //  Preferences
    protected $destructiveDelete;
    protected $perPage;
    protected $defaultSortColumn;
    protected $defaultSortOrder;

    // --------------------------------------------------------------------------

    /**
     * @todo : this is copied directly from CodeIgniter - consider removing.
     * __get
     *
     * Allows models to access CI's loaded classes using the same
     * syntax as controllers.
     *
     * @param   string
     * @access private
     */
    public function __get($key)
    {
        $CI =& get_instance();
        return $CI->$key;
    }

    /**
     * --------------------------------------------------------------------------
     * CONSTRUCTOR && DESTRUCTOR
     * The constructor preps common variables and sets the model up for user.
     * The destructor clears
     * --------------------------------------------------------------------------
     */

    /**
     * Base constructor.
     */
    public function __construct()
    {
        //  Ensure models all have access to the global user_model
        if (function_exists('getUserObject')) {
            $this->user_model = getUserObject();
        }

        // --------------------------------------------------------------------------

        //  Define defaults
        $this->clearErrors();
        $this->destructiveDelete      = true;
        $this->tableIdColumn          = 'id';
        $this->tableSlugColumn        = 'slug';
        $this->tableLabelColumn       = 'label';
        $this->tableCreatedColumn     = 'created';
        $this->tableCreatedByColumn   = 'created_by';
        $this->tableModifiedColumn    = 'modified';
        $this->tableModifiedByColumn  = 'modified_by';
        $this->tableDeletedColumn     = 'is_deleted';
        $this->tableAutoSetTimestamps = true;
        $this->tableAutoSetSlugs      = false;
        $this->perPage                = 50;
        $this->searchableFields       = array();
        $this->defaultSortColumn      = null;
        $this->defaultSortOrder       = 'ASC';

        // --------------------------------------------------------------------------

        /**
         * Set up default searchable fields. Each field is passed directly to the
         * `column` parameter in getCountCommon() so can be in any form accepted by that.
         *
         * @todo  allow some sort of cleansing callback so that models can prep the
         * search string if needed.
         */
        $this->searchableFields[] = $this->tablePrefix . $this->tableIdColumn;
        $this->searchableFields[] = $this->tablePrefix . $this->tableLabelColumn;

        // --------------------------------------------------------------------------

        //  Default expandable fields
        if (!empty($this->tableCreatedByColumn)) {
            $this->addExpandableField(
                array(
                    'trigger'     => 'created_by',
                    'type'        => self::EXPANDABLE_TYPE_SINGLE,
                    'property'    => 'created_by',
                    'model'       => 'User',
                    'provider'    => 'nailsapp/module-auth',
                    'id_column'   => 'created_by'
                )
            );
        }

        if (!empty($this->tableModifiedByColumn)) {
            $this->addExpandableField(
                array(
                    'trigger'     => 'modified_by',
                    'type'        => self::EXPANDABLE_TYPE_SINGLE,
                    'property'    => 'modified_by',
                    'model'       => 'User',
                    'provider'    => 'nailsapp/module-auth',
                    'id_column'   => 'modified_by'
                )
            );
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Destruct the model
     * @return void
     */
    public function __destruct()
    {
        /**
         * @todo: decide whether this is necessary; should caches be persistent;
         * gut says yes.
         */

        $this->clearCache();
    }

    // --------------------------------------------------------------------------

    /**
     * Inject the user object, private by convention - only really used by a few core Nails classes
     * @param object $user The user object
     * @return void
     */
    public function setUserObject(&$user)
    {
        $this->user = $user;
    }

    /**
     * --------------------------------------------------------------------------
     *
     * MUTATION METHODS
     * These methods provide a consistent interface for creating, and manipulating
     * objects that this model represents. These methods should be extended if any
     * custom functionality is required.
     *
     * See the docs for more info
     * @TODO: link to docs
     *
     * --------------------------------------------------------------------------
     */

    /**
     * Creates a new object
     * @param  array   $aData         The data to create the object with
     * @param  boolean $bReturnObject Whether to return just the new ID or the full object
     * @return mixed
     * @throws ModelException
     */
    public function create($aData = array(), $bReturnObject = false)
    {
        if (!$this->table) {
            throw new ModelException(get_called_class() . '::create() Table variable not set', 1);
        }

        $oDb = Factory::service('Database');

        // --------------------------------------------------------------------------

        if ($this->tableAutoSetTimestamps) {

            $oDate = Factory::factory('DateTime');

            if (empty($aData[$this->tableCreatedColumn])) {
                $aData[$this->tableCreatedColumn] = $oDate->format('Y-m-d H:i:s');
            }
            if (empty($aData[$this->tableModifiedColumn])) {
                $aData[$this->tableModifiedColumn] = $oDate->format('Y-m-d H:i:s');
            }

            if ($this->user_model->isLoggedIn()) {

                if (empty($aData[$this->tableCreatedByColumn])) {
                    $aData[$this->tableCreatedByColumn] = activeUser('id');
                }
                if (empty($aData[$this->tableModifiedByColumn])) {
                    $aData[$this->tableModifiedByColumn] = activeUser('id');
                }

            } else {

                if (empty($aData[$this->tableCreatedByColumn])) {
                    $oDb->set($this->tableCreatedByColumn, null);
                    $aData[$this->tableCreatedByColumn] = null;
                }
                if (empty($aData[$this->tableModifiedByColumn])) {
                    $aData[$this->tableModifiedByColumn] = null;
                }
            }

        }

        if (!empty($this->tableAutoSetSlugs) && empty($aData[$this->tableSlugColumn])) {

            if (empty($this->tableSlugColumn)) {
                throw new ModelException(get_called_class() . '::create() Slug column variable not set', 1);
            }

            if (empty($this->tableLabelColumn)) {
                throw new ModelException(get_called_class() . '::create() Label column variable not set', 1);
            }

            if (empty($aData[$this->tableLabelColumn])) {

                throw new ModelException(
                    get_called_class() . '::create() "' . $this->tableLabelColumn .
                    '" is required when automatically generting slugs.',
                    1
                );
            }

            $aData[$this->tableSlugColumn] = $this->generateSlug($aData[$this->tableLabelColumn]);
        }

        if (!empty($aData)) {

            unset($aData['id']);
            foreach ($aData as $sColumn => $mValue) {
                if (is_array($mValue)) {

                    $mSetValue = isset($mValue[0]) ? $mValue[0] : null;
                    $bEscape   = isset($mValue[1]) ? (bool) $mValue[1] : true;

                    $oDb->set($sColumn, $mSetValue, $bEscape);

                } else {

                    $oDb->set($sColumn, $mValue);
                }
            }
        }

        $oDb->insert($this->table);

        if ($oDb->affected_rows()) {

            $iId = $oDb->insert_id();

            // --------------------------------------------------------------------------

            //  @todo - Hook into the Event service and automatically trigger CREATED event

            // --------------------------------------------------------------------------

            if ($bReturnObject) {

                return $this->getById($iId);

            } else {

                return $iId;
            }

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Updates an existing object
     * @param  integer|array $mIds  The ID (or array of IDs) of the object(s) to update
     * @param  array         $aData The data to update the object(s) with
     * @return boolean
     * @throws ModelException
     */
    public function update($mIds, $aData = array())
    {
        if (!$this->table) {

            throw new ModelException(get_called_class() . '::update() Table variable not set', 1);

        } else {

            $sPrefix = $this->tablePrefix ? $this->tablePrefix . '.' : '';
            $sTable  = $this->tablePrefix ? $this->table . ' ' . $this->tablePrefix : $this->table;
        }

        $oDb = Factory::service('Database');

        // --------------------------------------------------------------------------

        if ($this->tableAutoSetTimestamps) {

            if (empty($aData[$this->tableModifiedColumn])) {
                $oDate = Factory::factory('DateTime');
                $aData[$sPrefix . $this->tableModifiedColumn] = $oDate->format('Y-m-d H:i:s');
            }

            if ($this->user_model->isLoggedIn()) {

                if (empty($aData[$this->tableModifiedByColumn])) {
                    $aData[$sPrefix . $this->tableModifiedByColumn] = activeUser('id');
                }

            } else {

                if (empty($aData[$this->tableModifiedByColumn])) {
                    $aData[$sPrefix . $this->tableModifiedByColumn] = null;
                }
            }
        }

        if (!empty($this->tableAutoSetSlugs) && empty($aData[$this->tableSlugColumn])) {

            if (is_array($mIds)) {
                throw new ModelException('Cannot autogenerate slugs when updating multiple items.', 1);
            }

            if (empty($this->tableSlugColumn)) {
                throw new ModelException(get_called_class() . '::update() Slug column variable not set', 1);
            }

            if (empty($this->tableLabelColumn)) {
                throw new ModelException(get_called_class() . '::update() Label column variable not set', 1);
            }

            /**
             *  We only need to re-generate the slug field if there's a label being passed. If
             *  no label, assume slug is unchanged.
             */
            if (!empty($aData[$this->tableLabelColumn])) {
                $aData[$sPrefix . $this->tableSlugColumn] = $this->generateSlug(
                    $aData[$this->tableLabelColumn],
                    '',
                    '',
                    null,
                    null,
                    $mIds
                );
            }
        }

        if (!empty($aData)) {

            unset($aData['id']);
            foreach ($aData as $sColumn => $mValue) {
                if (is_array($mValue)) {

                    $mSetValue = isset($mValue[0]) ? $mValue[0] : null;
                    $bEscape   = isset($mValue[1]) ? (bool) $mValue[1] : true;

                    $oDb->set($sColumn, $mSetValue, $bEscape);

                } else {

                    $oDb->set($sColumn, $mValue);
                }
            }
        }

        // --------------------------------------------------------------------------

        if (is_array($mIds)) {
            $oDb->where_in($sPrefix . 'id', $mIds);
        } else {
            $oDb->where($sPrefix . 'id', $mIds);
        }

        $bResult = $oDb->update($sTable);

        // --------------------------------------------------------------------------

        //  @todo - Hook into the Event service and automatically trigger UPDATED event

        // --------------------------------------------------------------------------

        return $bResult;
    }

    // --------------------------------------------------------------------------

    /**
     * Marks an object as deleted
     *
     * If destructive deletion is enabled then this method will permanently
     * destroy the object. If Non-destructive deletion is enabled then the
     * $this->tableDeletedColumn field will be set to true.
     *
     * @param  integer|array $mIds The ID (or an array of IDs) of the object(s) to mark as deleted
     * @return boolean
     * @throws ModelException
     */
    public function delete($mIds)
    {
        //  Perform this check here so the error message is more easily traced.
        if (!$this->table) {
            throw new ModelException(get_called_class() . '::delete() Table variable not set', 1);
        }

        // --------------------------------------------------------------------------

        if ($this->destructiveDelete) {

            //  Destructive delete; nuke that row.
            $bResult = $this->destroy($mIds);

        } else {

            //  Non-destructive delete, update the flag
            $aData = array(
                $this->tableDeletedColumn => true
            );

            $bResult = $this->update($mIds, $aData);
        }

        // --------------------------------------------------------------------------

        //  @todo - Hook into the Event service and automatically trigger DELETED event

        // --------------------------------------------------------------------------

        return $bResult;
    }

    // --------------------------------------------------------------------------

    /**
     * Unmarks an object as deleted
     *
     * If destructive deletion is enabled then this method will return false.
     * If Non-destructive deletion is enabled then the $this->tableDeletedColumn
     * field will be set to false.
     *
     * @param  int     $iId The ID of the object to restore
     * @return boolean
     * @throws ModelException
     */
    public function restore($iId)
    {
        //  Perform this check here so the error message is more easily traced.
        if (!$this->table) {
            throw new ModelException(get_called_class() . '::restore() Table variable not set', 1);
        }

        // --------------------------------------------------------------------------

        if ($this->destructiveDelete) {

            //  Destructive delete; can't be resurrecting the dead.
            return false;

        } else {

            //  Non-destructive delete, update the flag
            $aData = array(
                $this->tableDeletedColumn => false
            );

            $bResult = $this->update($iId, $aData);

            // --------------------------------------------------------------------------

            //  @todo - Hook into the Event service and automatically trigger RESTORED event

            // --------------------------------------------------------------------------

            return $bResult;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Permanently deletes an object
     *
     * This method will attempt to delete the row from the table, regardless of whether
     * destructive deletion is enabled or not.
     *
     * @param  integer|array $mIds The ID (or array of IDs) of the object to destroy
     * @return boolean
     * @throws ModelException
     */
    public function destroy($mIds)
    {
        //  Perform this check here so the error message is more easily traced.
        if (!$this->table) {
            throw new ModelException(get_called_class() . '::destroy() Table variable not set', 1);
        }

        $oDb = Factory::service('Database');

        // --------------------------------------------------------------------------

        if (is_array($mIds)) {
            $oDb->where_in('id', $mIds);
        } else {
            $oDb->where('id', $mIds);
        }

        $bResult = $oDb->delete($this->table);

        // --------------------------------------------------------------------------

        //  @todo - Hook into the Event service and automatically trigger DESTROYED event

        // --------------------------------------------------------------------------

        return $bResult;
    }

    /**
     * --------------------------------------------------------------------------
     *
     * RETRIEVAL & COUNTING METHODS
     * These methods provide a consistent interface for retrieving and counting objects
     *
     * --------------------------------------------------------------------------
     */

    /**
     * Fetches all objects, optionally paginated. Returns the basic query object with no formatting.
     * @param  int    $page           The page number of the results, if null then no pagination
     * @param  int    $perPage        How many items per page of paginated results
     * @param  mixed  $data           Any data to pass to getCountCommon()
     * @param  bool   $includeDeleted If non-destructive delete is enabled then this flag allows you to include deleted items
     * @return object
     * @throws ModelException
     */
    public function getAllRawQuery($page = null, $perPage = null, $data = array(), $includeDeleted = false)
    {
        if (!$this->table) {

            throw new ModelException(get_called_class() . '::getAllRawQuery() Table variable not set', 1);

        } else {

            $table = $this->tablePrefix ? $this->table . ' ' . $this->tablePrefix : $this->table;
        }

        // --------------------------------------------------------------------------

        $oDb = Factory::service('Database');

        // --------------------------------------------------------------------------

        //  Define the default sorting
        if (empty($data['sort']) && !empty($this->defaultSortColumn)) {
            $data['sort'] = array(
                $this->tablePrefix . '.' . $this->defaultSortColumn,
                $this->defaultSortOrder
            );
        }

        // --------------------------------------------------------------------------

        //  Apply common items; pass $data
        $this->getCountCommon($data);

        // --------------------------------------------------------------------------

        //  Facilitate pagination
        if (!is_null($page)) {

            /**
             * Adjust the page variable, reduce by one so that the offset is calculated
             * correctly. Make sure we don't go into negative numbers
             */

            $page--;
            $page = $page < 0 ? 0 : $page;

            //  Work out what the offset should be
            $perPage = is_null($perPage) ? $this->perPage : (int) $perPage;
            $offset   = $page * $perPage;

            $oDb->limit($perPage, $offset);
        }

        // --------------------------------------------------------------------------

        //  If non-destructive delete is enabled then apply the delete query
        if (!$this->destructiveDelete && !$includeDeleted) {

            $sPrefix = $this->tablePrefix ? $this->tablePrefix . '.' : '';
            $oDb->where($sPrefix . $this->tableDeletedColumn, false);
        }

        return $oDb->get($table);
    }

    // --------------------------------------------------------------------------

    /**
     * Fetches all objects and formats them, optionally paginated
     * @param int    $iPage           The page number of the results, if null then no pagination
     * @param int    $iPerPage        How many items per page of paginated results
     * @param mixed  $aData           Any data to pass to getCountCommon()
     * @param bool   $bIncludeDeleted If non-destructive delete is enabled then this flag allows you to include deleted items
     * @return array
     */
    public function getAll($iPage = null, $iPerPage = null, $aData = array(), $bIncludeDeleted = false)
    {
        $oResults   = $this->getAllRawQuery($iPage, $iPerPage, $aData, $bIncludeDeleted);
        $aResults   = $oResults->result();
        $numResults = count($aResults);

        /**
         * Handle requests for expanding objects.
         * there are two types of expandable objects:
         *  1. Fields which are an ID, these can be expanded by the appropriate model (1 to 1)
         *  2. Query a model for items which reference this item's ID  (1 to many)
         */

        if (!empty($this->aExpandableFields)) {

            foreach ($this->aExpandableFields as $oExpandableField) {

                $bAutoExpand       = $oExpandableField->auto_expand;
                $bExpandAll        = false;
                $bExpandForTrigger = false;

                //  If we're not auto-expanding, check if we should expand based on the `expand` index of $aData
                if (!$bAutoExpand && array_key_exists('expand', $aData)) {
                    $bExpandAll        = $aData['expand'] == static::EXPAND_ALL;
                    if (!$bExpandAll && is_array($aData['expand'])) {
                        $bExpandForTrigger = in_array($oExpandableField->trigger, $aData['expand']);
                    }
                }

                if ($bAutoExpand || $bExpandAll || $bExpandForTrigger) {

                    if ($oExpandableField->type === static::EXPANDABLE_TYPE_SINGLE) {

                        $this->getSingleAssociatedItem(
                            $aResults,
                            $oExpandableField->id_column,
                            $oExpandableField->property,
                            $oExpandableField->model,
                            $oExpandableField->provider
                        );

                    } else if ($oExpandableField->type === static::EXPANDABLE_TYPE_MANY) {

                        $this->getManyAssociatedItems(
                            $aResults,
                            $oExpandableField->property,
                            $oExpandableField->id_column,
                            $oExpandableField->model,
                            $oExpandableField->provider
                        );
                    }
                }
            }
        }

        for ($i = 0; $i < $numResults; $i++) {
            $this->formatObject($aResults[$i], $aData);
        }

        return $aResults;
    }

    // --------------------------------------------------------------------------

    /**
     * Fetches all objects as a flat array
     * @param  int     $page           The page number of the results
     * @param  int     $perPage        The number of items per page
     * @param  array   $data           Any data to pass to getCountCommon()
     * @param  boolean $includeDeleted Whether or not to include deleted items
     * @return array
     * @throws ModelException
     */
    public function getAllFlat($page = null, $perPage = null, $data = array(), $includeDeleted = false)
    {
        $items = $this->getAll($page, $perPage, $data, $includeDeleted);
        $out   = array();

        //  Nothing returned? Skip the rest of this method, it's pointless.
        if (!$items) {

            return array();
        }

        // --------------------------------------------------------------------------

        //  Test columns
        $oTest = reset($items);

        if (!property_exists($oTest, $this->tableLabelColumn)) {
            throw new ModelException(
                get_called_class() . '::getAllFlat() "' . $this->tableLabelColumn . '" is not a valid column.',
                1
            );
        }

        if (!property_exists($oTest, $this->tableLabelColumn)) {
            throw new ModelException(
                get_called_class() . '::getAllFlat() "' . $this->tableIdColumn . '" is not a valid column.',
                1
            );
        }

        unset($oTest);

        // --------------------------------------------------------------------------

        foreach ($items as $item) {
            $out[$item->{$this->tableIdColumn}] = $item->{$this->tableLabelColumn};
        }

        return $out;
    }

    // --------------------------------------------------------------------------

    /**
     * Fetch an object by it's ID
     * @param  int      $iId   The ID of the object to fetch
     * @param  mixed    $aData Any data to pass to getCountCommon()
     * @return mixed           stdClass on success, false on failure
     * @throws ModelException
     */
    public function getById($iId, $aData = array())
    {
        if (!$this->table) {
            throw new ModelException(get_called_class() . '::getById() Table variable not set', 1);
        }

        // --------------------------------------------------------------------------

        $sPrefix = $this->tablePrefix ? $this->tablePrefix . '.' : '';

        // --------------------------------------------------------------------------

        if (empty($iId)) {
            return false;
        }

        // --------------------------------------------------------------------------

        if (!isset($aData['where'])) {
            $aData['where'] = array();
        }

        $aData['where'][] = array($sPrefix . $this->tableIdColumn, $iId);

        // --------------------------------------------------------------------------

        $aResult = $this->getAll(null, null, $aData);

        // --------------------------------------------------------------------------

        if (empty($aResult)) {
            return false;
        }

        // --------------------------------------------------------------------------

        return $aResult[0];
    }

    // --------------------------------------------------------------------------

    /**
     * Fetch objects by their IDs
     * @param  array $aIds  An array of IDs to fetch
     * @param  mixed $aData Any data to pass to getCountCommon()
     * @return array
     * @throws ModelException
     */
    public function getByIds($aIds, $aData = array())
    {
        if (!$this->table) {
            throw new ModelException(get_called_class() . '::getByIds() Table variable not set', 1);
        }

        // --------------------------------------------------------------------------

        $sPrefix = $this->tablePrefix ? $this->tablePrefix . '.' : '';

        // --------------------------------------------------------------------------

        if (empty($aIds)) {
            return array();
        }

        // --------------------------------------------------------------------------

        if (!isset($aData['where_in'])) {

            $aData['where_in'] = array();
        }

        $aData['where_in'][] = array($sPrefix . $this->tableIdColumn, $aIds);

        // --------------------------------------------------------------------------

        return $this->getAll(null, null, $aData);
    }

    // --------------------------------------------------------------------------

    /**
     * Fetch an object by it's slug
     * @param  string   $sSlug The slug of the object to fetch
     * @param  array    $aData Any data to pass to getCountCommon()
     * @return \stdClass
     * @throws ModelException
     */
    public function getBySlug($sSlug, $aData = array())
    {
        if (!$this->table) {
            throw new ModelException(get_called_class() . '::getBySlug() Table variable not set', 1);
        }

        // --------------------------------------------------------------------------

        $sPrefix = $this->tablePrefix ? $this->tablePrefix . '.' : '';

        // --------------------------------------------------------------------------

        if (empty($sSlug)) {
            return false;
        }

        // --------------------------------------------------------------------------

        if (!isset($aData['where'])) {

            $aData['where'] = array();
        }

        $aData['where'][] = array($sPrefix . $this->tableSlugColumn, $sSlug);

        // --------------------------------------------------------------------------

        $aResult = $this->getAll(null, null, $aData);

        // --------------------------------------------------------------------------

        if (empty($aResult)) {

            return false;
        }

        // --------------------------------------------------------------------------

        return $aResult[0];
    }

    // --------------------------------------------------------------------------

    /**
     * Fetch objects by their slugs
     * @param  array $aSlugs An array of slugs to fetch
     * @param  array $aData  Any data to pass to getCountCommon()
     * @return array
     * @throws ModelException
     */
    public function getBySlugs($aSlugs, $aData = array())
    {
        if (!$this->table) {
            throw new ModelException(get_called_class() . '::getBySlugs() Table variable not set', 1);
        }

        // --------------------------------------------------------------------------

        $sPrefix = $this->tablePrefix ? $this->tablePrefix . '.' : '';

        // --------------------------------------------------------------------------

        if (empty($aSlugs)) {
            return array();
        }

        // --------------------------------------------------------------------------

        if (!isset($aData['where_in'])) {

            $aData['where_in'] = array();
        }

        $aData['where_in'][] = array($sPrefix . $this->tableSlugColumn, $aSlugs);

        // --------------------------------------------------------------------------

        return $this->getAll(null, null, $aData, false);
    }

    // --------------------------------------------------------------------------

    /**
     * Fetch an object by it's id or slug
     *
     * Auto-detects whether to use the ID or slug as the selector when fetching
     * an object. Note that this method uses is_numeric() to determine whether
     * an ID or a slug has been passed, thus numeric slugs (which are against
     * Nails style guidelines) will be interpreted incorrectly.
     *
     * @param  mixed    $mIdSlug The ID or slug of the object to fetch
     * @param  array    $aData   Any data to pass to getCountCommon()
     * @return \stdClass
     */
    public function getByIdOrSlug($mIdSlug, $aData = array())
    {
        if (is_numeric($mIdSlug)) {

            return $this->getById($mIdSlug, $aData);

        } else {

            return $this->getBySlug($mIdSlug, $aData);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Get associated content for the items in the result set where the the relationship is 1 to 1 and the binding
     * is made in the item object (i.e current item contains the associated item's ID)
     * @param  array   &$aItems                  The result set of items
     * @param  string  $sAssociatedItemIdColumn  Which property in the result set contains the associated content's ID
     * @param  string  $sItemProperty            What property of each item to assign the associated content
     * @param  string  $sAssociatedModel         The name of the model which handles the associated content
     * @param  string  $sAssociatedModelProvider Which module provides the associated model
     * @param  array   $aAssociatedModelData     Data to pass to the associated model's getByIds method()
     * @param  boolean $bUnsetOriginalProperty   Whether to remove the original property (i.e the property defined by $sAssociatedItemIdColumn)
     * @return void
     */
    public function getSingleAssociatedItem(
        &$aItems,
        $sAssociatedItemIdColumn,
        $sItemProperty,
        $sAssociatedModel,
        $sAssociatedModelProvider,
        $aAssociatedModelData = array(),
        $bUnsetOriginalProperty = true
    ) {
        if (!empty($aItems)) {

            $oAssociatedModel   = Factory::model($sAssociatedModel, $sAssociatedModelProvider);
            $aAssociatedItemIds = array();

            foreach ($aItems as $oItem) {

                //  Note the associated item's ID
                $aAssociatedItemIds[] = $oItem->{$sAssociatedItemIdColumn};

                //  Set the base property, only if it's not already set
                if (!property_exists($oItem, $sItemProperty)) {
                    $oItem->{$sItemProperty} = null;
                }
            }

            $aAssociatedItemIds = array_unique($aAssociatedItemIds);
            $aAssociatedItemIds = array_filter($aAssociatedItemIds);
            $aAssociatedItems   = $oAssociatedModel->getByIds($aAssociatedItemIds, $aAssociatedModelData);

            foreach ($aItems as $oItem) {
                foreach ($aAssociatedItems as $oAssociatedItem) {
                    if ($oItem->{$sAssociatedItemIdColumn} == $oAssociatedItem->id) {
                        $oItem->{$sItemProperty} = $oAssociatedItem;
                    }
                }

                /**
                 * Unset the original property, but only if it's not the same as the new property,
                 * otherwise we'll remove the property which was just set!
                 */
                if ($bUnsetOriginalProperty && $sAssociatedItemIdColumn !== $sItemProperty) {
                    unset($oItem->{$sAssociatedItemIdColumn});
                }
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Get associated content for the items in the result set where the the relationship is 1 to many
     * @param  array  &$aItems                     The result set of items
     * @param  string $sItemProperty               What property of each item to assign the associated content
     * @param  string $sAssociatedItemIdColumn     Which property in the associated content which contains the item's ID
     * @param  string $sAssociatedModel            The name of the model which handles the associated content
     * @param  string $sAssociatedModelProvider    Which module provides the associated model
     * @param  array  $aAssociatedModelData        Data to pass to the associated model's getByIds method()
     * @return void
     */
    protected function getManyAssociatedItems(
        &$aItems,
        $sItemProperty,
        $sAssociatedItemIdColumn,
        $sAssociatedModel,
        $sAssociatedModelProvider,
        $aAssociatedModelData = array()
    ) {
        if (!empty($aItems)) {

            $oAssociatedModel = Factory::model($sAssociatedModel, $sAssociatedModelProvider);

            $aItemIds = array();
            foreach ($aItems as $oItem) {

                //  Note the ID
                $aItemIds[] = $oItem->id;

                //  Set the base property
                $oItem->{$sItemProperty}        = new \stdClass();
                $oItem->{$sItemProperty}->count = 0;
                $oItem->{$sItemProperty}->data  = array();
            }

            if (empty($aAssociatedModelData['where_in'])) {
                $aAssociatedModelData['where_in'] = array();
            }

            $aAssociatedModelData['where_in'][] = array(
                $oAssociatedModel->getTablePrefix() . '.' . $sAssociatedItemIdColumn,
                $aItemIds
            );

            $aAssociatedItems = $oAssociatedModel->getAll(null, null, $aAssociatedModelData);

            foreach ($aItems as $oItem) {
                foreach ($aAssociatedItems as $oAssociatedItem) {
                    if ($oItem->id == $oAssociatedItem->{$sAssociatedItemIdColumn}) {
                        $oItem->{$sItemProperty}->data[] = $oAssociatedItem;
                        $oItem->{$sItemProperty}->count++;
                    }
                }
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Count associated content for the items in the result set where the the relationship is 1 to many
     * @param  array  &$aItems                     The result set of items
     * @param  string $sItemProperty               What property of each item to assign the associated content
     * @param  string $sAssociatedItemIdColumn     Which property in the associated content which contains the item's ID
     * @param  string $sAssociatedModel            The name of the model which handles the associated content
     * @param  string $sAssociatedModelProvider    Which module provides the associated model
     * @param  array  $aAssociatedModelData        Data to pass to the associated model's getByIds method()
     * @return void
     */
    protected function countManyAssociatedItems(
        &$aItems,
        $sItemProperty,
        $sAssociatedItemIdColumn,
        $sAssociatedModel,
        $sAssociatedModelProvider,
        $aAssociatedModelData = array()
    ) {
        if (!empty($aItems)) {

            $oAssociatedModel = Factory::model($sAssociatedModel, $sAssociatedModelProvider);

            $aItemIds = array();
            foreach ($aItems as $oItem) {

                //  Note the ID
                $aItemIds[] = $oItem->id;

                //  Set the base property
                $oItem->{$sItemProperty} = 0;
            }

            //  Limit the select
            $aAssociatedModelData['select'] = array(
                $oAssociatedModel->getTablePrefix() . '.id',
                $oAssociatedModel->getTablePrefix() . '.' . $sAssociatedItemIdColumn
            );

            if (empty($aAssociatedModelData['where_in'])) {
                $aAssociatedModelData['where_in'] = array();
            }

            $aAssociatedModelData['where_in'][] = array($sAssociatedItemIdColumn, $aItemIds);

            $aAssociatedItems = $oAssociatedModel->getAll(null, null, $aAssociatedModelData);

            foreach ($aItems as $oItem) {
                foreach ($aAssociatedItems as $oAssociatedItem) {
                    if ($oItem->id == $oAssociatedItem->{$sAssociatedItemIdColumn}) {
                        $oItem->{$sItemProperty}++;
                    }
                }
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Get associated content for the items in the result set using a taxonomy table
     * @param  array  &$aItems                     The result set of items
     * @param  string $sItemProperty               What property of each item to assign the associated content
     * @param  string $sTaxonomyModel              The name of the model which handles the taxonomy relationships
     * @param  string $sTaxonomyModelProvider      Which module provides the taxonomy model
     * @param  string $sAssociatedModel            The name of the model which handles the associated content
     * @param  string $sAssociatedModelProvider    Which module provides the associated model
     * @param  array  $aAssociatedModelData        Data to pass to the associated model's getByIds method()
     * @param  string $sTaxonomyItemIdColumn       The name of the column in the taxonomy table for the item ID
     * @param  string $sTaxonomyAssociatedIdColumn The name of the column in the taxonomy table for the associated ID
     * @return void
     */
    protected function getManyAssociatedItemsWithTaxonomy(
        &$aItems,
        $sItemProperty,
        $sTaxonomyModel,
        $sTaxonomyModelProvider,
        $sAssociatedModel,
        $sAssociatedModelProvider,
        $aAssociatedModelData = array(),
        $sTaxonomyItemIdColumn = 'item_id',
        $sTaxonomyAssociatedIdColumn = 'associated_id'
    ) {
        if (!empty($aItems)) {

            //  Load the required models
            $oTaxonomyModel   = Factory::model($sTaxonomyModel, $sTaxonomyModelProvider);
            $oAssociatedModel = Factory::model($sAssociatedModel, $sAssociatedModelProvider);

            //  Extract all the item IDs and set the base array for the associated content
            $aItemIds = array();
            foreach ($aItems as $oItem) {

                //  Note the ID
                $aItemIds[] = $oItem->id;

                //  Set the base property
                $oItem->{$sItemProperty}        = new \stdClass();
                $oItem->{$sItemProperty}->count = 0;
                $oItem->{$sItemProperty}->data  = array();
            }

            //  Get all associations for items in the resultset
            $aTaxonomy = $oTaxonomyModel->getAll(
                null,
                null,
                array(
                    'where_in' => array(
                        array($sTaxonomyItemIdColumn, $aItemIds)
                    )
                )
            );

            if (!empty($aTaxonomy)) {

                //  Extract the IDs of the associated content
                $aAssociatedIds = array();
                foreach ($aTaxonomy as $oTaxonomy) {
                    $aAssociatedIds[] = $oTaxonomy->{$sTaxonomyAssociatedIdColumn};
                }
                $aAssociatedIds = array_unique($aAssociatedIds);

                if (!empty($aAssociatedIds)) {

                    //  Get all associated content
                    $aAssociated = $oAssociatedModel->getByIds($aAssociatedIds, $aAssociatedModelData);

                    if (!empty($aAssociated)) {

                        //  Merge associated content into items
                        foreach ($aAssociated as $oAssociated) {
                            foreach ($aTaxonomy as $oTaxonomy) {
                                if ($oTaxonomy->{$sTaxonomyAssociatedIdColumn} == $oAssociated->id) {
                                    foreach ($aItems as $oItem) {
                                        if ($oTaxonomy->{$sTaxonomyItemIdColumn} == $oItem->id) {
                                            $oItem->{$sItemProperty}->data[] = $oAssociated;
                                            $oItem->{$sItemProperty}->count++;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Save associated items for an object
     * @param  integer $iItemId                  The ID of the main item
     * @param  array   $aAssociatedItems         The data to save, multi-dimensional array of data
     * @param  string  $sAssociatedItemIdColumn  The name of the ID column in the associated table
     * @param  string  $sAssociatedModel         The name of the model which is responsible for associated items
     * @param  string  $sAssociatedModelProvider What module provide the associated item model
     * @return boolean
     * @throws ModelException
     */
    protected function saveAsscociatedItems(
        $iItemId,
        $aAssociatedItems,
        $sAssociatedItemIdColumn,
        $sAssociatedModel,
        $sAssociatedModelProvider
    ) {
        $oAssociatedItemModel = Factory::model($sAssociatedModel, $sAssociatedModelProvider);
        $aTouchedIds          = array();
        $aExistingItemIds     = array();

        //  Get IDs of current items, we'll compare these later to see which ones to delete.
        $aData = array(
            'where' => array(
                array($oAssociatedItemModel->getTablePrefix() . '.' . $sAssociatedItemIdColumn, $iItemId)
            )
        );

        $aExistingItems = $oAssociatedItemModel->getAll(null, null, $aData);
        foreach ($aExistingItems as $oExistingItem) {
            $aExistingItemIds[] = $oExistingItem->id;
        }

        // --------------------------------------------------------------------------

        //  Update/insert all known items
        foreach ($aAssociatedItems as $aAssociatedItem) {

            $aAssociatedItem = (array) $aAssociatedItem;

            if (!empty($aAssociatedItem['id'])) {

                //  Safety, no updating of IDs
                $iAssociatedItemId = $aAssociatedItem['id'];
                unset($aAssociatedItem['id']);

                //  Perform update
                if (!$oAssociatedItemModel->update($iAssociatedItemId, $aAssociatedItem)) {
                    throw new ModelException('Failed to update associated item.', 1);
                } else {
                    $aTouchedIds[] = $iAssociatedItemId;
                }

            } else {

                //  Safety, no setting of IDs
                unset($aAssociatedItem['id']);

                //  Ensure the related column is set
                $aAssociatedItem[$sAssociatedItemIdColumn] = $iItemId;

                //  Perform the create
                $iAssociatedItemId = $oAssociatedItemModel->create($aAssociatedItem);
                if (!$iAssociatedItemId) {

                    throw new ModelException('Failed to create associated item.', 1);

                } else {

                    $aTouchedIds[] = $iAssociatedItemId;
                }
            }
        }

        // --------------------------------------------------------------------------

        //  We want to delete items which are no longer in use
        $aIdDiff = array_diff($aExistingItemIds, $aTouchedIds);

        //  Delete those we no longer require
        if (!empty($aIdDiff)) {
            if (!$oAssociatedItemModel->delete($aIdDiff)) {
                throw new ModelException('Failed to delete old associated items.', 1);
            }
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Counts all objects
     * @param  array   $aData           An array of data to pass to getCountCommon()
     * @param  boolean $bIncludeDeleted Whether to include deleted objects or not
     * @return integer
     * @throws ModelException
     */
    public function countAll($aData = array(), $bIncludeDeleted = false)
    {
        if (!$this->table) {

            throw new ModelException(get_called_class() . '::countAll() Table variable not set', 1);

        } else {

            $table  = $this->tablePrefix ? $this->table . ' ' . $this->tablePrefix : $this->table;
        }

        $oDb = Factory::service('Database');

        // --------------------------------------------------------------------------

        //  Apply common items
        $this->getCountCommon($aData);

        // --------------------------------------------------------------------------

        //  If non-destructive delete is enabled then apply the delete query
        if (!$this->destructiveDelete && !$bIncludeDeleted) {
            $sPrefix = $this->tablePrefix ? $this->tablePrefix . '.' : '';
            $oDb->where($sPrefix . $this->tableDeletedColumn, false);
        }

        // --------------------------------------------------------------------------

        return $oDb->count_all_results($table);
    }

    // --------------------------------------------------------------------------

    /**
     * Searches for objects, optionally paginated.
     * @param  string    $sKeywords       The search term
     * @param  int       $iPage           The page number of the results, if null then no pagination
     * @param  int       $iPerPage        How many items per page of paginated results
     * @param  mixed     $aData           Any data to pass to getCountCommon()
     * @param  bool      $bIncludeDeleted If non-destructive delete is enabled then this flag allows you to include deleted items
     * @return \stdClass
     */
    public function search($sKeywords, $iPage = null, $iPerPage = null, $aData = array(), $bIncludeDeleted = false)
    {
        //  @todo: specify searchable fields in constructor and generate this manually
        if (empty($aData['or_like'])) {
            $aData['or_like'] = array();
        }

        foreach ($this->searchableFields as $mField) {
            $aData['or_like'][] = array(
                'column' => $mField,
                'value'  => $sKeywords
            );
        }

        $oOut          = new \stdClass();
        $oOut->page    = $iPage;
        $oOut->perPage = $iPerPage;
        $oOut->total   = $this->countAll($aData);
        $oOut->data    = $this->getAll($iPage, $iPerPage, $aData, $bIncludeDeleted);

        return $oOut;
    }

    /**
     * --------------------------------------------------------------------------
     *
     * HELPER METHODS
     * These methods provide additional functionality to models
     *
     * --------------------------------------------------------------------------
     */

    /**
     * This method provides the functionality to generate a unique slug for an item in the database.
     * @param string $label    The label from which to generate a slug
     * @param string $prefix   Any prefix to add to the slug
     * @param string $suffix   Any suffix to add to the slug
     * @param string $table    The table to use defaults to $this->table
     * @param string $column   The column to use, defaults to $this->tableSlugColumn
     * @param int    $ignoreId An ID to ignore when searching
     * @param string $idColumn The column to use for the ID, defaults to $this->tableIdColumn
     * @return string
     * @throws ModelException
     */
    protected function generateSlug($label, $prefix = '', $suffix = '', $table = null, $column = null, $ignoreId = null, $idColumn = null)
    {
        //  Perform this check here so the error message is more easily traced.
        if (is_null($table)) {

            if (!$this->table) {
                throw new ModelException(get_called_class() . '::generateSlug() Table variable not set', 1);
            }

            $table = $this->table;
        }

        if (is_null($column)) {

            if (!$this->tableSlugColumn) {
                throw new ModelException(get_called_class() . '::generateSlug() Column variable not set', 1);
            }

            $column = $this->tableSlugColumn;
        }

        // --------------------------------------------------------------------------

        $counter = 0;
        $oDb     = Factory::service('Database');

        do {

            $slug = url_title(str_replace('/', '-', $label), 'dash', true);

            if ($counter) {

                $slugTest = $prefix . $slug . $suffix . '-' . $counter;

            } else {

                $slugTest = $prefix . $slug . $suffix;
            }

            if ($ignoreId) {

                $sIdColumn = $idColumn ? $idColumn : $this->tableIdColumn;
                $oDb->where($sIdColumn . ' !=', $ignoreId);
            }

            $oDb->where($column, $slugTest);
            $counter++;

        } while ($oDb->count_all_results($table));

        return $slugTest;
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a single object
     *
     * The getAll() method iterates over each returned item with this method so as to
     * correctly format the output. Use this to cast integers and booleans and/or organise data into objects.
     *
     * @param  object $oObj      A reference to the object being formatted.
     * @param  array  $aData     The same data array which is passed to _getCountCommon, for reference if needed
     * @param  array  $aIntegers Fields which should be cast as integers if numerical and not null
     * @param  array  $aBools    Fields which should be cast as booleans if not null
     * @param  array  $aFloats   Fields which should be cast as floats if not null
     * @return void
     */
    protected function formatObject(
        &$oObj,
        $aData = array(),
        $aIntegers = array(),
        $aBools = array(),
        $aFloats = array()
    ) {

        $aIntegers   = (array) $aIntegers;
        $aIntegers[] = $this->tableIdColumn;
        $aIntegers[] = $this->tableCreatedByColumn;
        $aIntegers[] = $this->tableModifiedByColumn;
        $aIntegers[] = 'parent_id';
        $aIntegers[] = 'user_id';
        $aIntegers[] = 'order';

        foreach ($aIntegers as $sProperty) {
            if (property_exists($oObj, $sProperty) && is_numeric($oObj->{$sProperty}) && !is_null($oObj->{$sProperty})) {
                $oObj->{$sProperty} = (int) $oObj->{$sProperty};
            }
        }

        // --------------------------------------------------------------------------

        $aBools   = (array) $aBools;
        $aBools[] = $this->tableDeletedColumn;
        $aBools[] = 'is_active';
        $aBools[] = 'is_published';

        foreach ($aBools as $sProperty) {
            if (property_exists($oObj, $sProperty) && !is_null($oObj->{$sProperty})) {
                $oObj->{$sProperty} = (bool) $oObj->{$sProperty};
            }
        }

        // --------------------------------------------------------------------------

        $aFloats = (array) $aFloats;

        foreach ($aFloats as $sProperty) {
            if (property_exists($oObj, $sProperty) && is_numeric($oObj->{$sProperty}) && !is_null($oObj->{$sProperty})) {
                $oObj->{$sProperty} = (float) $oObj->{$sProperty};
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns protected property $table
     * @return string
     */
    public function getTableName()
    {
        return $this->table;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns protected property $tablePrefix
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    // --------------------------------------------------------------------------

    /**
     * Define expandable objects
     * @param $aOptions array An array describing the expandable field
     * @throws ModelException
     */
    protected function addExpandableField($aOptions)
    {
        //  Validation
        if (!array_key_exists('trigger', $aOptions)) {
            throw new ModelException('Expandable fields must define a "trigger".');
        }

        if (!array_key_exists('type', $aOptions)) {
            throw new ModelException('Expandable fields must define a "type".');
        }

        if (!array_key_exists('property', $aOptions)) {
            throw new ModelException('Expandable fields must define a "property".');
        }

        if (!array_key_exists('model', $aOptions)) {
            throw new ModelException('Expandable fields must define a "model".');
        }

        if (!array_key_exists('provider', $aOptions)) {
            throw new ModelException('Expandable fields must define a "provider".');
        }

        if (!array_key_exists('id_column', $aOptions)) {
            throw new ModelException('Expandable fields must define a "id_column".');
        }

        $this->aExpandableFields[] = (object) array(

            //  the text which triggers this expansion
            'trigger'  => $aOptions['trigger'],

            //  The type of expansion: single or many
            'type' => $aOptions['type'],

            //  What property to assign the results of the expansion to
            'property' => $aOptions['property'],

            //  Which model to use for the expansion
            'model' => $aOptions['model'],

            //  The provider of the model
            'provider' => $aOptions['provider'],

            /**
             * The ID column to use; for EXPANDABLE_TYPE_SINGLE this is property of the
             * parent object which contains the ID, for EXPANDABLE_TYPE_MANY, this is the
             * property of the child object which contains the parent's ID.
             */
            'id_column' => $aOptions['id_column'],

            //  Whether the field is expanded by default
            'auto_expand' => !empty($aOptions['auto_expand']),
        );
    }
}
