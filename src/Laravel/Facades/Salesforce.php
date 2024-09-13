<?php namespace CEE\Salesforce\Laravel\Facades;

use CEE\Salesforce\ForceDotCom\Results\BasicResult;
use CEE\Salesforce\ForceDotCom\Results\QueryResult;
use CEE\Salesforce\ForceDotCom\SforceBaseClient;
use CEE\Salesforce\ForceDotCom\SforceEnterpriseClient;
use Illuminate\Support\Facades\Facade;

/**
 * Class SalesforceFacade
 *
 * @package  CEE\Salesforce\Laravel
 *
 * Facade for the Salesforce service
 * @see SforceEnterpriseClient
 *
 * @method static QueryResult query(string $query)
 * @see SforceBaseClient::query()
 * @method static BasicResult create(array $sObjects, string $type)
 * @see SforceEnterpriseClient::create()
 * @method static BasicResult update(array $sObjects, string $type)
 * @see SforceEnterpriseClient::update()
 * @method static BasicResult delete(array $ids)
 * @see SforceBaseClient::delete()
 */
class Salesforce extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'salesforce';
    }
}
