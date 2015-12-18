<?php

namespace Magefix\Fixtures;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Mage_Core_Model_Abstract;
use Magefix\Magento\Store\Scope as MagentoStoreScope;
use Magefix\Fixture\Factory\Builder as FixtureBuilder;
use Magefix\Exceptions\UnavailableHook;
use Magefix\Exceptions\NullFixtureId;

/**
 * Class Registry
 *
 * @package Magefix\Fixtures
 * @author  Carlo Tasca <ctasca@sessiondigital.com>
 */
trait Registry
{
    /**
     * Registry collection
     *
     * @var array
     */
    static private $_registry = [];

    /**
     * @var array
     */
    private $_contexts = [];

    /**
     * Register a new variable
     *
     * @param string $key
     * @param mixed  $value
     *
     * @throws Mage_Core_Exception
     */
    public static function register($key, $value)
    {
        self::$_registry[$key] = $value;
    }

    /**
     * Retrieve a value from registry by a key
     *
     * @param string $key
     *
     * @return mixed
     */
    public static function registry($key)
    {
        if (isset(self::$_registry[$key])) {
            return self::$_registry[$key];
        }

        return null;
    }

    /**
     * Unregister a variable from register by key
     *
     * @param string $key
     */
    public static function unregister($key)
    {
        if (isset(self::$_registry[$key])) {
            if (is_object(self::$_registry[$key]) && (method_exists(self::$_registry[$key], '__destruct'))) {
                self::$_registry[$key]->__destruct();
            }
            unset(self::$_registry[$key]);
        }
    }

    /**
     * @return array
     */
    public static function getRegistry()
    {
        return self::$_registry;
    }

    /**
     * Reset static registry
     */
    public static function reset()
    {
        self::$_registry = [];
    }

    /**
     * Register fixture reference
     *
     * @param $type
     * @param $fixtureId
     * @param $hook
     *
     * @throws UnavailableHook
     */
    public static function registerFixture($type, $fixtureId, $hook)
    {
        if ($hook) {
            $lowercaseHook = strtolower($hook);
            self::_registerFixture($type, $fixtureId, trim($lowercaseHook, '@'));
        }
    }

    /**
     * @AfterFeature
     */
    public static function afterFeatureFixturesCleanup()
    {
        self::_cleanupFixtureByHook('afterfeature');
    }

    /**
     * @AfterScenario
     */
    public function afterScenarioFixturesCleanup()
    {
        self::_cleanupFixtureByHook('afterscenario');
    }

    /**
     * @AfterStep
     */
    public function afterStepFixturesCleanup()
    {
        self::_cleanupFixtureByHook('afterstep');
    }

    /**
     * Register feature fixture reference
     *
     * @param $type
     * @param $fixtureId
     * @param $hook
     *
     * @throws NullFixtureId
     */
    protected static function _registerFixture($type, $fixtureId, $hook)
    {
        self::_throwNullFixtureIdException($fixtureId);
        self::register($type . "_{$hook}_" . $fixtureId, $fixtureId);
    }

    /**
     * @param string feature|scenario|step $hook
     */
    protected static function _cleanupFixtureByHook($hook)
    {
        $registry = self::_getBuilderRegistry();

        MagentoStoreScope::setAdminStoreScope();
        self::_iterateRegistry($registry, $hook);
        MagentoStoreScope::setCurrentStoreScope();
    }

    /**
     * @param $registry
     * @param $hook
     *
     * @throws \Exception
     */
    protected static function _iterateRegistry($registry, $hook)
    {
        $registryIterator         = new RegistryIterator($registry);
        $registryIteratorIterator = $registryIterator->getIterator();

        while ($registryIteratorIterator->valid()) {
            $key        = $registryIteratorIterator->key();
            $entry      = $registryIteratorIterator->current();
            $entryMatch = $registryIterator->isEntryMatch($hook, $key);

            if (!empty($entryMatch) && isset($entryMatch[1])) {
                self::_echoRegistryChangeMessage(
                    $registryIterator->getMageModelForMatch($entryMatch[1]), $entryMatch[1], $entry, $key
                );
            }

            $registryIteratorIterator->next();
        }
    }


    /**
     * @param Mage_Core_Model_Abstract $model
     * @param                          $fixtureType
     * @param                          $entry
     * @param                          $key
     */
    protected static function _echoRegistryChangeMessage(Mage_Core_Model_Abstract $model, $fixtureType, $entry, $key)
    {
        echo self::_deleteAndUnregisterFixture(
            $model, $fixtureType, $entry, $key
        );
    }

    /**
     * @return array
     */
    protected static function _getBuilderRegistry()
    {
        return FixtureBuilder::getRegistry();
    }

    /**
     * @param Mage_Core_Model_Abstract $model
     * @param                          $fixtureType
     * @param                          $entry
     * @param                          $key
     *
     * @return string
     * @throws \Exception
     */
    protected static function _deleteAndUnregisterFixture(Mage_Core_Model_Abstract $model, $fixtureType, $entry, $key)
    {
        $fixture = $model->load((int)$entry);
        $fixture->delete();
        FixtureBuilder::unregister($key);

        return self::_deleteFixtureMessage($fixtureType, $entry);
    }

    /**
     * @param string $fixtureType
     * @param string $entry
     *
     * @return string
     */
    protected static function _deleteFixtureMessage($fixtureType, $entry)
    {
        return "-- DELETED {$fixtureType} fixture with ID {$entry}\n";
    }

    /**
     * @param $fixtureId
     *
     * @throws NullFixtureId
     */
    protected static function _throwNullFixtureIdException($fixtureId)
    {
        if (!$fixtureId) {
            throw new NullFixtureId('Could not register fixture. Fixture id is null.');
        }
    }

    /**
     * Get environment context classes
     *
     * @BeforeScenario
     *
     * @param BeforeScenarioScope $scope
     */
    public function getContexts(BeforeScenarioScope $scope)
    {
        $environment = $scope->getEnvironment();

        foreach ($environment->getContextClasses() as $class) {
            $name                   = strtolower(preg_replace('~.*\\\\(\w+)Context~', '\1', $class));
            $this->_contexts[$name] = $environment->getContext($class);
        }
    }
}
