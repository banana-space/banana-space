<?php

class CSSJanusBenchmark {

	public function run() {
		foreach (self::getFixtures() as $name => $data) {
			$iterations = 1000;
			$this->outputIntro($name, $data, $iterations);
			$total = 0;
			$max = -INF;
			$i = 0;
			for ($i = 1; $i <= $iterations; $i++) {
				$start = microtime(true);
				CSSJanus::transform($data, [ 'transformDirInUrl' => true ]);
				$took = ( microtime(true) - $start) * 1000;
				$max = max($max, $took);
				$total += ( microtime(true) - $start) * 1000;
			}
			$this->outputStat($total, $max, $iterations);
		}
	}

	protected function outputIntro($name, $data, $iterations) {
		echo "\n## {$name}\n"
			. "- data length: " . $this->formatSize(strlen($data)) . "\n"
			. "- data hash:   " . hash('fnv132', $data) . "\n"
			. "- iterations:  " . $iterations . "\n";
	}

	protected function outputStat($total, $max, $iterations) {
		$mean = $total / $iterations; // in milliseconds
		$ratePerSecond = 1.0 / ( $mean / 1000.0 );
		echo "- max:         " . sprintf('%.2fms', $max) . "\n";
		echo "- mean:        " . sprintf('%.2fms', $mean) . "\n";
		echo "- rate:        " . sprintf('%.0f/s', $ratePerSecond) . "\n";
	}

	private function formatSize($size) {
		$i = floor(log($size, 1024));
		return round($size / pow(1024, $i), [0,0,2,2,3][$i]) . ' ' . ['B','KB','MB','GB','TB'][$i];
	}

	protected function getFixtures() {
		$fixtures = [
			'mediawiki-legacy-shared' => [
				'version' => '1064426',
				'src' => 'https://github.com/wikimedia/mediawiki/raw/1064426'
					. '/resources/src/mediawiki.legacy/shared.css',
			],
			'ooui-core' => [
				'version' => '130344b',
				'src' => 'https://github.com/wikimedia/mediawiki/raw/130344b'
					. '/resources/lib/oojs-ui/oojs-ui-core-wikimediaui.css',
			],
		];
		$result = [];
		$dir = __DIR__;
		foreach ($fixtures as $name => $desc) {
			$file = "{$dir}/data-fixture-{$name}.{$desc['version']}.css";
			if (!is_readable($file)) {
				array_map('unlink', glob("{$dir}/data-fixture-{$name}.*"));
				$data = file_get_contents($desc['src']);
				if ($data === false) {
					throw new Exception("Failed to fetch fixture: {$name}");
				}
				file_put_contents($file, $data);
			} else {
				$data = file_get_contents($file);
			}
			$result[$name] = $data;
		}
		return $result;
	}
}
