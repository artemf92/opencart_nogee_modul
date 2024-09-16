<?php
namespace Opencart\Catalog\Controller\Common;

class Nogee {
	private $document;

	public function __construct($document) {
		$this->document = $document;
	}

	public function getMinifiedStyles() {
		$arStyles = [];
		$excludedPatterns = ['custom.css', 'fontawesome/css/all.min.css', 'stylesheet/cache'];
		$css_files = $this->document->getStyles();
		$minified_css_file = DIR_APPLICATION . 'view/stylesheet/minified_site_css.min.css';
		$cacheAction = isset($_GET['clear_cache']) && $_GET['clear_cache'] > 0;
		$cache_timestamp_file = DIR_CACHE . 'cache_timestamp.txt';

		$css_content = '';
		foreach ($css_files as $file) {
			$path = explode('?', $file['href']);
			$path = $path[0];
			$relative_path = str_replace(DIR_OPENCART, '', $path);
			$file_name = basename($path);

			$isExcluded = false;
			foreach ($excludedPatterns as $pattern) {
				if (preg_match('#' . preg_quote($pattern, '#') . '#', $relative_path) || preg_match('#' . preg_quote($pattern, '#') . '#', $file_name)) {
					$isExcluded = true;
					break;
				}
			}

			if ($isExcluded) {
				$arStyles[] = $file;
			} else {
				if ($cacheAction) {
					$css_content .= '/** file:' . $path .' **/ ';
					$css_content .= $this->minifyCssContent(file_get_contents(DIR_OPENCART . $path));
				}
			}
		}

		if ($cacheAction) file_put_contents($minified_css_file, $css_content);

		$minified_style = [
			'href'  => 'catalog/view/stylesheet/minified_site_css.min.css?ver=' . file_get_contents($cache_timestamp_file),
			'rel'   => 'stylesheet',
			'media' => 'all'
		];
		array_unshift($arStyles, $minified_style);

		return $arStyles;
	}

	public function getMinifiedJs($type) {
		$arJs = [];
		$js_files = $this->document->getScripts($type);
		$excludedPatterns = ['common.js', 'javascript/cache', 'extension/paypal'];
		$js_minified_dir = DIR_APPLICATION . 'view/javascript/minified/';
		$cacheAction = isset($_GET['clear_cache']) && $_GET['clear_cache'] > 0;
		$cache_timestamp_file = DIR_CACHE . 'cache_timestamp.txt';

		foreach ($js_files as $file) {
			$path = explode('?', $file['href']);
			$path = $path[0];
			$file_name = basename($path);
			$base_url = parse_url(HTTP_SERVER, PHP_URL_HOST);

			if ($this->isExcludedFromType($file_name, $type)) continue;

			if ($this->isExcluded($path, $file_name, $excludedPatterns) || !$this->isLocalUrl($path, $base_url)) {
				$arJs[] = $file;
			} else {
				if (strpos($file_name, '.min.') === false) {
					$minified_file = str_replace('.js', '.min.js', $file_name);
				} else {
					$minified_file = $file_name;
				}
				
				if ($cacheAction) {
					$js_content = '/** file:' . $path .' **/ ';
					$js_content = JSMin::minify(file_get_contents(DIR_OPENCART . $path));

					if (!is_dir($js_minified_dir)) {
						mkdir($js_minified_dir, 0755, true);
					}

					file_put_contents($js_minified_dir . $minified_file, $js_content);
				}

				$arJs[] = ['href' => 'catalog/view/javascript/minified/' . $minified_file .'?ver=' . file_get_contents($cache_timestamp_file)];
			}
		}

		return $arJs;
	}

	public function minifyCssContent($css) {
		$css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
		$css = preg_replace('/\s*([{}|:;,])\s+/', '$1', $css);
		$css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $css);

		return trim($css);
	}

	private function isExcluded($path, $file_name, $excludedStyles) {
		foreach ($excludedStyles as $excluded) {
			if (strpos($path, $excluded) !== false || strpos($file_name, $excluded) !== false) {
				return true;
			}
		}
		return false;
	}

	private function isLocalUrl($url, $base_url) {
		$parsed_url = parse_url($url);
		if (!isset($parsed_url['scheme'])) {
			$parsed_url['scheme'] = parse_url(HTTP_SERVER, PHP_URL_SCHEME);
		}
		$url_host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
		return $url_host == '' || $url_host === $base_url;
	}

	private function isExcludedFromType($name, $type) {
		$arEx = [
			'bootstrap.bundle.min.js',
			'moment.min.js',
			'moment-with-locales.min.css',
			'daterangepicker.js',
			'typed.min.js',
			'theme.js',
			'support.js',
			'common.js',
		];

		return $type == 'header' && in_array($name, $arEx);
	}
}