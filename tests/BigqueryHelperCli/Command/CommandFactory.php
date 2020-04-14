<?php


namespace BigqueryHelperCli\Command;


class CommandFactory
{
	/**
	 * @param $cliParams
	 * @return Command
	 * @throws \Exception
	 */
	public static function createCommand($cliParams)
	{
		if ($cliParams[0] === 'export-dataset-info')
		{
			return new ExportDatasetInfoCommand(['cliParams' => $cliParams]);
		}
		else if($cliParams[0] === 'export-dataset-access')
		{
			return new ExportDatasetAccessCommand(['cliParams' => $cliParams]);
		}
		throw new \Exception("cannot create cli command");
	}
}