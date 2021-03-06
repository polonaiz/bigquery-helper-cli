<?php


namespace BigqueryHelperCli;


// https://cloud.google.com/bigquery/docs/reference/rest/v2/datasets#resource

class DatasetAccess
{
	private array $data;

	public function __construct($data = [])
	{
		$this->data = $data;
	}

	/**
	 * @param array $entry
	 * @return DatasetAccess
	 * @throws \Exception
	 */
	public function includeAccess($entry)
	{
		$this->data['access'][] = $entry;
		return $this;
	}

	public function excludeAccessEntry($entry)
	{
		$access = [];
		foreach ($this->data['access'] as $accessEntry)
		{
			if (\json_encode($accessEntry) === \json_encode($entry))
			{
				continue;
			}
			$access[] = $accessEntry;
		}
		$this->data['access'] = $access;
		return $this;
	}

	public function excludeSpecialGroup()
	{
		$access = [];
		foreach ($this->data['access'] as $accessEntry)
		{
			if (isset($accessEntry['specialGroup']))
			{
				continue;
			}
			$access[] = $accessEntry;
		}
		$this->data['access'] = $access;
		return $this;
	}

	public function excludeOwner()
	{
		$access = [];
		foreach ($this->data['access'] as $accessEntry)
		{
			if (isset($accessEntry['role']) && $accessEntry['role'] === 'OWNER')
			{
				continue;
			}
			$access[] = $accessEntry;
		}
		$this->data['access'] = $access;
		return $this;
	}

	public function excludeView()
	{
		$access = [];
		foreach ($this->data['access'] as $accessEntry)
		{
			if (isset($accessEntry['view']))
			{
				continue;
			}
			$access[] = $accessEntry;
		}
		$this->data['access'] = $access;
		return $this;
	}

	public
	function toArray()
	{
		return $this->data;
	}

	public static
	function accessEntryComparator($accessEntry1, $accessEntry2)
	{
		return \strcmp(\json_encode($accessEntry1), \json_encode($accessEntry2));
	}

	public static
	function expandConfigDataset($datasetId, $accessControlConfiguration)
	{
		$access = [];

		// build group dictionary
		$customGroupDict = [];
		foreach ($accessControlConfiguration['customGroups'] as $group)
		{
			$customGroupDict[$group['customGroupId']] = $group;
		}


		foreach ($accessControlConfiguration['datasetAccessConfigList'] as $datasetAccessConfig)
		{
			$matched = \preg_match($datasetAccessConfig['datasetIdPattern'], $datasetId);
			if ($matched === false || $matched === 0)
			{
				continue;
			}

			foreach ($datasetAccessConfig['access'] as $accessEntry)
			{
				$access = \array_merge($access, \array_map(
					function ($member) use ($accessEntry)
					{
						return ['role' => $accessEntry['role']] + ['userByEmail' => $member['userByEmail']];
					},
					$customGroupDict[$accessEntry['userByCustomGroup']]['members']
				));
			}
			unset($accessEntry);
		}

		(new ArrayHandler($access))
			->sort([self::class, 'accessEntryComparator'])
			->uniq([self::class, 'accessEntryComparator']);

		return [
			'datasetId' => $datasetId,
			'access' => $access
		];
	}

	/**
	 * @param $datasetAccessData1
	 * @param $datasetAccessData2
	 * @return array
	 * @throws \Exception
	 */
	public static
	function diff($datasetAccessData1, $datasetAccessData2)
	{
		if ($datasetAccessData1['datasetId'] !== $datasetAccessData2['datasetId'])
		{
			throw new \Exception("FAILURE");
		}

		$datasetAccessPatch = [
			'datasetId' => $datasetAccessData1['datasetId'],
			'accessPatchList' => []
		];

		foreach ($datasetAccessData1['access'] as $accessEntry1)
		{
			if ((new ArrayHandler($datasetAccessData2['access']))
					->exists($accessEntry1, [self::class, 'accessEntryComparator']) == false)
			{
				if (isset($accessEntry1['userByEmail']))
				{
					$datasetAccessPatch['accessPatchList'][] = [
						'type' => '-', 'role' => $accessEntry1['role'], 'userByEmail' => $accessEntry1['userByEmail']
					];
				}
			}
		}

		foreach ($datasetAccessData2['access'] as $accessEntry2)
		{
			if ((new ArrayHandler($datasetAccessData1['access']))
					->exists($accessEntry2, [self::class, 'accessEntryComparator']) == false)
			{
				$datasetAccessPatch['accessPatchList'][] = [
					'type' => '+', 'role' => $accessEntry2['role'], 'userByEmail' => $accessEntry2['userByEmail']
				];
			}
		}
		return $datasetAccessPatch;
	}
}