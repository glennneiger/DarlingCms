<?php
/**
 * Created by PhpStorm.
 * User: sevidmusic
 * Date: 4/19/17
 * Time: 6:00 PM
 */

namespace DarlingCms\abstractions\crud;


abstract class AregisteredCrud implements \DarlingCms\interfaces\crud\Icrud
{
    /**
     * @var $registry array Registry of stored data.
     */
    protected $registry;

    public function __construct()
    {
        /* Initialize the registry. */
        $this->initializeRegistry();
    }

    /**
     * Initializes the registry.
     * @return bool True if registry was initialized, false otherwise.
     */
    private function initializeRegistry()
    {
        /* Check if the stored registry exists. */
        if ($this->read('registry') === false) {
            /* If the stored registry does not exist, create it. */
            $storageId = 'registry';
            $this->create('registry', array(
                $storageId => $this->generateRegistryData($storageId, 'array'),
            ));
        }
        /* Sync the internal and stored registries. */
        $this->registry = $this->read('registry');
        return isset($this->registry);
    }

    /**
     * Read data from storage.
     * @param string $storageId The data's storage id.
     * @return mixed The data, or false on failure.
     * Note: Since this crud implementation allows the saving of any type of data,
     * including boolean values, it is possible that read() will return false if
     * the data associated with the specified storage id is the boolean false.
     */
    final public function read(string $storageId)
    {
        return $this->unpack($this->query($storageId, 'load'));
    }

    /**
     * Unpack data packed by the pack() method.
     *
     * @param mixed $packedData The packed data.
     *
     * @return mixed The unpacked data, or false on failure.
     */
    abstract protected function unpack($packedData);

    /**
     * Query stored data. This method is the backbone of all implementations of this abstract class.
     * It is through the implementation of this method that the create(), read(), update(), and delete()
     * methods are able to run queries on stored data.
     *
     * @param string $storageId The storage id of the data to query.
     *
     * @param string $mode The query mode determines what kind of query is run. There are
     *                     three query modes(*) that all implementations of this method must
     *                     accommodate: save, load, and delete.
     *
     *                     (*) Note: Implementations may implement additional query modes but the
     *                               following three query modes: save, load, and delete; must
     *                               always be implemented.
     *
     *                     --- Query Modes ---
     *
     *                     'save':  This mode is responsible for storing data. It expects
     *                                the $data parameter to be the data that is to be stored.
     *
     *                     'load': This mode reads data from storage.
     *
     *                     'delete': This mode deletes stored data.
     *
     * @param mixed $data (optional) The data that is to be stored. Only required if query $mode is set to 'save'.
     *
     * @return mixed|bool Should return the result of the query, or false on failure.
     */
    abstract protected function query(string $storageId, string $mode, $data = null);

    /**
     * Store new data.
     * @param string $storageId An id to be assigned to the stored data.
     * @param mixed $data The data to store.
     * @return bool Return true if data was stored, false otherwise.
     */
    final public function create(string $storageId, $data)
    {
        /* Pack the data for storage. */
        $packedData = $this->pack($data);
        /* Run a save query on the packed data. */
        if ($this->query($storageId, 'save', $packedData) !== false) {
            /* Never register the registry, the registry is managed by the registry methods. */
            if ($storageId !== 'registry') {
                /* Register newly stored data in the registry. */
                return $this->register($storageId, $packedData);
            }
            /* When handling the registry, return true as long the save query was successful. */
            return true;
        }
        /* Return false if save query failed. */
        return false;
    }

    /**
     * Pack data for storage.
     * @param mixed $data The data to be packed.
     * @return bool True if data was packed successfully, false otherwise.
     */
    abstract protected function pack($data);

    /**
     * Register data in the registry.
     *
     * @return bool True if data was registered and registry was updated, false otherwise.
     */
    private function register(string $storageId, string $data)
    {
        /* Register data internally. */
        $this->registry[$storageId] = $this->generateRegistryData($storageId, $this->classify($data));
        /* If the data was registered internally, update the stored registry. */
        if (isset($this->registry[$storageId]) === true) {
            /* Return true if stored registry was updated, false otherwise. */
            return $this->update('registry', $this->registry);
        }
        /* Return false if data was not registered. */
        return false;
    }

    /**
     * @param string $storageId The storage id of the data to generate registry data for.
     * @param string $classification The classification of the data to generate registry data for.
     * @param array $additionalData Array of additional registry data that should be included in the
     *                              generated registry data.
     * @return array Array of registry data for the specified $storageId.
     */
    abstract protected function generateRegistryData(string $storageId, string $classification, array $additionalData = array());

    /**
     * Determines the type or class of a piece of packed data.
     * @param string $data The packed data to classify.
     * @return string The classification.
     */
    protected function classify(string $data)
    {
        $classification = gettype($this->unpack($data));
        if ($classification === 'object') {
            return get_class($this->unpack($data));
        }
        return $classification;
    }

    /**
     * Updates stored data.
     * @param string $storageId The storage id of the data to update.
     * @param mixed $newData The new data.
     * @return bool True if data was updated, false otherwise.
     */
    final public function update(string $storageId, $newData)
    {
        /* First attempt to delete the original data associated with the specified storage id. */
        if ($this->delete($storageId) === true) {
            /* If the original data was successfully deleted, return true if the new
               data was created, false otherwise. */
            return $this->create($storageId, $newData);
        }
        /* If original data was not deleted, return false. */
        return false;
    }

    /**
     * Delete data from storage.
     * @param string $storageId The storage id of the data to delete.
     * @return bool True if data was deleted, false otherwise.
     */
    final public function delete(string $storageId)
    {
        /* Run a delete query on the data associated with the specified storage id. */
        if ($this->query($storageId, 'delete') !== false) {
            /* Never un-register the registry, the registry is managed by the registry methods. */
            if ($storageId !== 'registry') {
                /* Return true if data was successfully deleted and un-registered, false otherwise. */
                return $this->unRegister($storageId);
            }
            /* When handling the registry, return true as long the delete query was successful. */
            return true;
        }
        /* Return false if delete query failed. */
        return false;
    }

    /**
     * Remove data from the registry.
     *
     * @return bool True if data was un-registered and registry was updated, false otherwise.
     */
    private function unRegister(string $storageId)
    {
        /* Un-register data from the internal registry. */
        unset($this->registry[$storageId]);
        /* If data was un-registered from the internal registry, update the stored registry. */
        if (isset($this->registry[$storageId]) === false) {
            /* Return true if stored registry was updated, false otherwise. */
            return $this->update('registry', $this->registry);
        }
        /* Return false if data was not registered. */
        return false;
    }

    /**
     * Returns the registry.
     * @return mixed The registry.
     */
    public function getRegistry()
    {
        return $this->registry;
    }

    /**
     * @param string $storageId The storage id of the data to return registry data for.
     * @param string $name (optional) Name of a specific piece of registry data to return.
     *                                If not set, then all of the registry data associated
     *                                with the specified storage id will be returned.
     * @return mixed The registry data for the data associated with the specified storage id.
     *               Note: False should be returned in the following circumstances:
     *                     1. If there is no registry data for the specified $storageId.
     *                     2. If there is no registry data associated with the specified
     *                        registry data $name.
     */
    abstract public function getRegistryData(string $storageId, string $name = '*');

}