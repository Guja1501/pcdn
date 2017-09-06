<?php

namespace Rangoo\Pcdn;

use Illuminate\Support\ServiceProvider;
use Rangoo\Pcdn\Console\RangooMaterialAdmin;

class RangooPcdnServiceProvider extends ServiceProvider
{
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = true;

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->singleton('command.rangoo.require.material-admin', function () {
			return new RangooMaterialAdmin;
		});

		$this->commands(['command.rangoo.require.material-admin']);
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return ['command.rangoo.require.material-admin'];
	}
}
