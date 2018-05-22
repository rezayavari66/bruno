<?php

namespace Rezayavari\Querybuilder;

use JsonSerializable;
use InvalidArgumentException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Router;
use Illuminate\Http\JsonResponse;
use Rezayavari\Architect\Architect;
use Illuminate\Http\Request;

abstract class LaravelController extends Controller
{
	/**
	 * Defaults
	 * @var array
	 */
	protected $defaults = [];
	
	/**
	 * Create a json response
	 * @param  mixed  $data
	 * @param  integer $statusCode
	 * @param  array  $headers
	 * @return Illuminate\Http\JsonResponse
	 */
	protected function response($data, $statusCode = 200, array $headers = [])
	{
		if ($data instanceof Arrayable && !$data instanceof JsonSerializable) {
			$data = $data->toArray();
		}
		
		return new JsonResponse($data, $statusCode, $headers);
	}
	
	/**
	 * Parse data using architect
	 * @param  mixed $data
	 * @param  array  $options
	 * @param  string $key
	 * @return mixed
	 */
	protected function parseData($data, array $options, $key = null)
	{
		$architect = new Architect();
		
		return $architect->parseData($data, $options['modes'], $key);
	}
	
	/**
	 * Page sort
	 * @param array $sort
	 * @return array
	 */
	protected function parseSort(array $sort) {
		return array_map(function($sort) {
			if (!isset($sort['direction'])) {
				$sort['direction'] = 'asc';
			}
			
			return $sort;
		}, $sort);
	}
	
	/**
	 * Parse include strings into resource and modes
	 * @param  array  $includes
	 * @return array The parsed resources and their respective modes
	 */
	protected function parseIncludes(array $includes)
	{
		$return = [
			'includes' => [],
			'modes' => []
		];
		
		foreach ($includes as $include) {
			//there are relation's name and mode in $expolode[0]
			//there are fields in $explode[1]
			$explode = explode(':', $include);
			
			$sub_explode = explode(',',$explode[0]);
			
			if (!isset($sub_explode[1])) {
				$sub_explode[1] = $this->defaults['mode'];
			}
			$include_res = $sub_explode[0];
			if (isset($explode[1]) ){
				$include_res = $sub_explode[0].':'.$explode[1];
			}
			$return['includes'][] = $include_res;
			$return['modes'][$explode[0]] = $sub_explode[1];
		}
		
		return $return;
	}
	
	/**
	 * Parse filter group strings into filters
	 * Filters are formatted as key:operator(value)
	 * Example: name:eq(esben)
	 * @param  array  $filter_groups
	 * @return array
	 */
	protected function parseFilterGroups(array $filter_groups)
	{
		$return = [];
		
		foreach($filter_groups as $group) {
			if (!array_key_exists('filters', $group)) {
				throw new InvalidArgumentException('Filter group does not have the \'filters\' key.');
			}
			
			$filters = array_map(function($filter){
				if (!isset($filter['not'])) {
					$filter['not'] = false;
				}
				
				return $filter;
			}, $group['filters']);
			
			$return[] = [
				'filters' => $filters,
				'or' => isset($group['or']) ? $group['or'] : false
			];
		}
		
		return $return;
	}
	
	/**
	 * Parse GET parameters into resource options
	 * @return array
	 */
	protected function parseResourceOptions($request = null)
	{
		if ($request === null) {
			$request = request();
		}
		
		$this->defaults = array_merge([
			'includes' => [],
			'sort' => [],
			'limit' => null,
			'page' => null,
			'mode' => 'embed',
			'filter_groups' => [],
			'group_by'=>'',
			'selects'=>''
		], $this->defaults);
		
		$includes = $this->parseIncludes($request->get('includes', $this->defaults['includes']));
		$sort = $this->parseSort($request->get('sort', $this->defaults['sort']));
		$limit = $request->get('limit', $this->defaults['limit']);
		$page = $request->get('page', $this->defaults['page']);
		$filter_groups = $this->parseFilterGroups($request->get('filter_groups', $this->defaults['filter_groups']));
		$groupBy = $request->get('group_by' , $this->defaults['group_by']);
		$selects = $request->get('selects' , $this->defaults['selects']);
		
		
		if ($page !== null && $limit === null) {
			throw new InvalidArgumentException('Cannot use page option without limit option');
		}
		
		return [
			'includes' => $includes['includes'],
			'modes' => $includes['modes'],
			'sort' => $sort,
			'limit' => $limit,
			'page' => $page,
			'filter_groups' => $filter_groups,
			'group_by'=>$groupBy,
			'selects'=>$selects
		];
	}
}
