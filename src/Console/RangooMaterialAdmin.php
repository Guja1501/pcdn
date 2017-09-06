<?php

namespace Rangoo\Pcdn\Console;

use Curl\Curl;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RangooMaterialAdmin extends Command {

	static private $domain = 'http://pcdn.rangoo.ge';

	/**
	 * pcdn zip file url
	 *
	 * @var string
	 */
	static private $url = 'http://pcdn.rangoo.ge/rangoo-material-admin.latest.zip';

	/**
	 * filename to use while working on zip
	 *
	 * @var string
	 */
	static private $filename = 'rangoo-material-admin.latest.zip';

	/**
	 * directory name for unzip and make jobs on zip
	 * at the end directory will removed
	 *
	 * @var string
	 */
	static private $directory = 'rangoo-material-admin-extracting-directory';

	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'require:material-admin
    					{--u|username= : Username on pcdn.rangoo.ge}
    					{--p|password= : Password on pcdn.rangoo.ge}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Install a material admin from pcdn.rangoo.ge';

	/**
	 * Create a new command instance.
	 *
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle() {
		if(!$this->validateUser($user = $this->getUser()))
			return false;

		return $this->changeDirectory()->download($user) ? $this->extract()
			->rsync()
			->clear()
			->done() : $this->clear();
//		return $this->changeDirectory()
//			->download($user)
//			->extract()
//			->rsync()
//			->clear()
//			->done();
	}

	/**
	 * validates user for pcdn.rangoo.ge
	 *
	 * @param $user
	 *
	 * @return bool
	 */
	private function validateUser($user) {
		$error = false;
		if(strlen($user->username) < 4 && $error = true)
			$this->error('Username must be at least 4 symbol.');

		if(strlen($user->password) < 6 && $error = true)
			$this->error('Password must be at least 6 symbol.');

		return !$error;
	}

	/**
	 * Check if $username and $password is in options filled
	 * or get them from ENV
	 *
	 * @return object
	 */
	private function getUser() {
		return (object)[
			'username' => $this->option('username') ?: env('PCDN_USERNAME', ''),
			'password' => $this->option('password') ?: env('PCDN_PASSWORD', '')
		];
	}

	private function download($user) {
		/** @var \Symfony\Component\Console\Helper\ProgressBar $progress */
		$progress = $this->output->createProgressBar(100);
		$progress->setFormat("%message%\n %current%/%max% [%bar%] %percent:3s%%\n");
		$progress->setMessage("Download is starting");
		$progress->setProgress(0);

		$curl = new Curl();
		$curl->setBasicAuthentication($user->username, $user->password);
		$curl->progress(function ($resource, $download_size, $downloaded, $upload_size, $uploaded) use ($progress) {
			$percent = intval($downloaded > 0 ? $downloaded / $download_size * 100 : 0);
			$progress->setMessage($percent < 100 ? 'Downloading' : 'Downloaded');
			$progress->setProgress($percent);
			$progress->advance(0);
		});
		$curl->download(self::$url, self::$filename);

		if($curl->error) {
			$progress->clear();
			$this->error($curl->errorMessage);

			return false;
		}

		$progress->finish();

		return $this;
	}

	private function changeDirectory() {
		$progress = $this->output->createProgressBar(4);
		$progress->setFormat("%percent:3s%% %message%\n");
		$progress->setMessage('Checking directory if does exists');
		$progress->advance();

		usleep(300000);

		if(!file_exists(self::$directory)) {
			$progress->setMessage('Directory does not exists, So creating new one');
			$progress->advance();
			usleep(300000);

			mkdir(self::$directory, 0775, true);

			$progress->setMessage('Directory created');
			$progress->advance();
			usleep(300000);
		}

		chdir(self::$directory);
		$progress->setMessage('Moved into directory: ' . self::$directory);
		$progress->advance();
		$progress->finish();

		return $this;
	}

	private function done() {
		$this->comment('Done');

		return $this;
	}

	private function clear() {
		chdir('..');

		$this->info('Removing garbage');

		$rm = new Process('rm -rf ' . self::$directory);
		$rm->run();

		if($rm->isSuccessful()) {
			return $this;
		}

		$this->error('Garbage can\'t be removed');

		return null;
	}

	private function rsync() {
		$data = [
			'public' => '..',
			'resources' => '..'
		];
		foreach($data as $what => $where) {
			$rsync = new Process("rsync -a {$what} {$where}");
			$rsync->run();

			if(!$rsync->isSuccessful())
				$this->error("Synchronize failed: {$what}");
			else
				$this->info("Synchronized successfully: {$what}");
		}

		return $this;
	}

	private function extract() {
		$zip = new \ZipArchive;
		$this->info("Opening zip");
		if($zip->open(self::$filename) === true) {
			$this->info('Starting extract');
			$zip->extractTo('.');
			$zip->close();

			return $this;
		}

		$this->error('Error occurs while extracting file');

		return null;
	}
}
