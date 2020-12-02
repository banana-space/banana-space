<?php
declare( strict_types = 1 );

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Wikimedia\Parsoid\ParserTests;

use Error;
use Wikimedia\Parsoid\Config\Api\ApiHelper;

/**
 * This class supports the implementation of Parser Tests in a standalone mode
 * and without network access.
 *
 * In standalone mode, the config and data transformations needed by Parsoid
 * cannot come from MediaWiki's database or its core classes.
 *
 * Without network access, we cannot fetch site configs or do data transformations
 * on a remote wiki. This class supports this by intercepting network requests
 * and returning mock responses based on cached site configs, hardcoded network
 * responses and config,
 *
 * So, this API helper should be used with the Parsoid\Config\Api* set of config classes
 * (and any subclasses derived from them).
 *
 * A lot of the responses here are tuned to what ParserTests needed. But, presumably
 * this can be used by PHP Unit tests as long as the specific set of mocked responses
 * satisfies the needs of those tests. Ideally, this class should NOT be updated for
 * anything but the needs of running parser tests.
 *
 * Alternatively, PHP Unit tests could bypass the Api* classes altogether and use
 * a (sub)set of mocked classes (Env, SiteConfig, PageConfig, DataAccess) if those
 * classes and the data they provide satisfies the needs of those tests.
 */
class MockApiHelper extends ApiHelper {
	// configuration to match PHP parserTests
	private const IMAGE_BASE_URL = 'http://example.com/images';
	private const IMAGE_DESC_URL = self::IMAGE_BASE_URL;
	private const FILE_PROPS = [
		'Foobar.jpg' => [
			'size' => 7881,
			'width' => 1941,
			'height' => 220,
			'bits' => 8,
			'mime' => 'image/jpeg'
		],
		'Thumb.png' => [
			'size' => 22589,
			'width' => 135,
			'height' => 135,
			'bits' => 8,
			'mime' => 'image/png'
		],
		'Foobar.svg' => [
			'size' => 12345,
			'width' => 240,
			'height' => 180,
			'bits' => 24,
			'mime' => 'image/svg+xml'
		],
		'LoremIpsum.djvu' => [
			'size' => 3249,
			'width' => 2480,
			'height' => 3508,
			'bits' => 8,
			'mime' => 'image/vnd.djvu'
		],
		'Video.ogv' => [
			'size' => 12345,
			'width' => 320,
			'height' => 240,
			'bits' => 0,
			'duration' => 160.733333333333,
			'mime' => 'application/ogg',
			'mediatype' => 'VIDEO'
		],
		'Audio.oga' => [
			'size' => 12345,
			'width' => 0,
			'height' => 0,
			'bits' => 0,
			'duration' => 160.733333333333,
			'mime' => 'application/ogg',
			'mediatype' => 'AUDIO'
		]
	];

	private $cachedConfigs = [];

	private static $MAIN_PAGE = [
		'query' => [
			'pages' => [
				[
					'pageid' => 1,
					'ns' => 0,
					'title' => 'Main Page',
					'revisions' => [
						[
							'revid' => 1,
							'parentid' => 0,
							'slots' => [
								'main' => [
									'contentmodel' => 'wikitext',
									'contentformat' => 'text/x-wiki',
									'content' => "<strong>MediaWiki has been successfully installed.</strong>\n\nConsult the [//meta.wikimedia.org/wiki/Help:Contents User's Guide] for information on using the wiki software.\n\n== Getting started ==\n* [//www.mediawiki.org/wiki/Special:MyLanguage/Manual:Configuration_settings Configuration settings list]\n* [//www.mediawiki.org/wiki/Special:MyLanguage/Manual:FAQ MediaWiki FAQ]\n* [https://lists.wikimedia.org/mailman/listinfo/mediawiki-announce MediaWiki release mailing list]\n* [//www.mediawiki.org/wiki/Special:MyLanguage/Localisation#Translation_resources Localise MediaWiki for your language]"
								]
							]
						]
					]
				]
			]
		]
	];

	// Old response structure, pre-mcr
	private static $OLD_RESPONSE = [
		'query' => [
			'pages' => [
				[
					'pageid' => 999,
					'ns' => 0,
					'title' => 'Old Response',
					'revisions' => [
						[
							'revid' => 999,
							'parentid' => 0,
							'contentmodel' => 'wikitext',
							'contentformat' => 'text/x-wiki',
							'*' => "<strong>MediaWiki was successfully installed.</strong>\n\nConsult the [//meta.wikimedia.org/wiki/Help:Contents User's Guide] for information on using the wiki software.\n\n== Getting started ==\n* [//www.mediawiki.org/wiki/Special:MyLanguage/Manual:Configuration_settings Configuration settings list]\n* [//www.mediawiki.org/wiki/Special:MyLanguage/Manual:FAQ MediaWiki FAQ]\n* [https://lists.wikimedia.org/mailman/listinfo/mediawiki-announce MediaWiki release mailing list]\n* [//www.mediawiki.org/wiki/Special:MyLanguage/Localisation#Translation_resources Localise MediaWiki for your language]"
						]
					]
				]
			]
		]
	];

	private static $JUNK_PAGE = [
		'query' => [
			'pages' => [
				[
					'pageid' => 2,
					'ns' => 0,
					'title' => 'Junk Page',
					'revisions' => [
						[
							'revid' => 2,
							'parentid' => 0,
							'slots' => [
								'main' => [
									'contentmodel' => 'wikitext',
									'contentformat' => 'text/x-wiki',
									'content' => '2. This is just some junk. See the comment above.'
								]
							]
						]
					]
				]
			]
		]
	];

	private static $LARGE_PAGE = [
		'query' => [
			'pages' => [
				[
					'pageid' => 3,
					'ns' => 0,
					'title' => 'Large_Page',
					'revisions' => [
						[
							'revid' => 3,
							'parentid' => 0,
							'slots' => [
								'main' => [
									'contentmodel' => 'wikitext',
									'contentformat' => 'text/x-wiki',
									/* content will be set separately */
								]
							]
						]
					]
				]
			]
		]
	];

	private static $REUSE_PAGE = [
		'query' => [
			'pages' => [
				[
					'pageid' => 100,
					'ns' => 0,
					'title' => 'Reuse_Page',
					'revisions' => [
						[
							'revid' => 100,
							'parentid' => 0,
							'slots' => [
								'main' => [
									'contentmodel' => 'wikitext',
									'contentformat' => 'text/x-wiki',
									'content' => '{{colours of the rainbow}}'
								]
							]
						]
					]
				]
			]
		]
	];

	private static $JSON_PAGE = [
		'query' => [
			'pages' => [
				[
					'pageid' => 101,
					'ns' => 0,
					'title' => 'JSON_Page',
					'revisions' => [
						[
							'revid' => 101,
							'parentid' => 0,
							'slots' => [
								'main' => [
									'contentmodel' => 'json',
									'contentformat' => 'text/json',
									'content' => '[1]'
								]
							]
						]
					]
				]
			]
		]
	];

	private static $LINT_PAGE = [
		'query' => [
			'pages' => [
				[
					'pageid' => 102,
					'ns' => 0,
					'title' => 'Lint Page',
					'revisions' => [
						[
							'revid' => 102,
							'parentid' => 0,
							'slots' => [
								'main' => [
									'contentmodel' => 'wikitext',
									'contentformat' => 'text/x-wiki',
									'content' => "{|\nhi\n|ho\n|}"
								]
							]
						]
					]
				]
			]
		]
	];

	private static $REDLINKS_PAGE = [
		'query' => [
			'pages' => [
				[
					'pageid' => 103,
					'ns' => 0,
					'title' => 'Redlinks Page',
					'revisions' => [
						[
							'revid' => 103,
							'parentid' => 0,
							'slots' => [
								'main' => [
									'contentmodel' => 'wikitext',
									'contentformat' => 'text/x-wiki',
									'content' => '[[Special:Version]] [[Doesnotexist]] [[Redirected]]'
								]
							]
						]
					]
				]
			]
		]
	];

	private static $VARIANT_PAGE = [
		'query' => [
			'pages' => [
				[
					'pageid' => 104,
					'ns' => 0,
					'pagelanguage' => 'sr',
					'pagelanguagedir' => 'ltr',
					'title' => 'Variant Page',
					'revisions' => [
						[
							'revid' => 104,
							'parentid' => 0,
							'slots' => [
								'main' => [
									'contentmodel' => 'wikitext',
									'contentformat' => 'text/x-wiki',
									'content' => "абвг abcd"
								]
							]
						]
					]
				]
			]
		]
	];

	private static $NOVARIANT_PAGE = [
		'query' => [
			'pages' => [
				[
					'pageid' => 105,
					'ns' => 0,
					'pagelanguage' => 'sr',
					'pagelanguagedir' => 'ltr',
					'title' => 'No Variant Page',
					'revisions' => [
						[
							'revid' => 105,
							'parentid' => 0,
							'slots' => [
								'main' => [
									'contentmodel' => 'wikitext',
									'contentformat' => 'text/x-wiki',
									'content' => "абвг abcd\n__NOCONTENTCONVERT__"
								]
							]
						]
					]
				]
			]
		]
	];

	private static $REVISION_PAGE = [
		'query' => [
			'pages' => [
				[
					'pageid' => 63,
					'ns' => 0,
					'title' => 'Revision ID',
					'revisions' => [
						[
							'revid' => 63,
							'parentid' => 0,
							'slots' => [
								'main' => [
									'contentmodel' => 'wikitext',
									'contentformat' => 'text/x-wiki',
									'content' => '{{REVISIONID}}'
								]
							]
						]
					]
				]
			]
		]
	];

	private static $missingTitles = [ 'Doesnotexist' ];
	private static $specialTitles = [ 'Special:Version' ];
	private static $redirectTitles = [ 'Redirected' ];
	private static $disambigTitles = [ 'Disambiguation' ];

	private const FNAMES = [
		'Image:Foobar.jpg' => 'Foobar.jpg',
		'Datei:Foobar.jpg' => 'Foobar.jpg',
		'File:Foobar.jpg' => 'Foobar.jpg',
		'Archivo:Foobar.jpg' => 'Foobar.jpg',
		'Mynd:Foobar.jpg' => 'Foobar.jpg',
		"Датотека:Foobar.jpg" => 'Foobar.jpg',
		'Image:Foobar.svg' => 'Foobar.svg',
		'File:Foobar.svg' => 'Foobar.svg',
		'Image:Thumb.png' => 'Thumb.png',
		'File:Thumb.png' => 'Thumb.png',
		'File:LoremIpsum.djvu' => 'LoremIpsum.djvu',
		'File:Video.ogv' => 'Video.ogv',
		'File:Audio.oga' => 'Audio.oga'
	];

	private const PNAMES = [
		'Image:Foobar.jpg' => 'File:Foobar.jpg',
		'Image:Foobar.svg' => 'File:Foobar.svg',
		'Image:Thumb.png' => 'File:Thumb.png'
	];

	// This templatedata description only provides a subset of fields
	// that mediawiki API returns. Parsoid only uses the format and
	// paramOrder fields at this point, so keeping these lean.
	private static $templateData = [
		'Template:NoFormatWithParamOrder' => [
			'paramOrder' => [ 'f0', 'f1', 'unused2', 'f2', 'unused3' ]
		],
		'Template:InlineTplNoParamOrder' => [
			'format' => 'inline'
		],
		'Template:BlockTplNoParamOrder' => [
			'format' => 'block'
		],
		'Template:InlineTplWithParamOrder' => [
			'format' => 'inline',
			'paramOrder' => [ 'f1', 'f2' ]
		],
		'Template:BlockTplWithParamOrder' => [
			'format' => 'block',
			'paramOrder' => [ 'f1', 'f2' ]
		],
		'Template:WithParamOrderAndAliases' => [
			'params' => [
				'f1' => [ 'aliases' => [ 'f4', 'f3' ] ]
			],
			'paramOrder' => [ 'f1', 'f2' ]
		],
		'Template:InlineFormattedTpl_1' => [
			'format' => '{{_|_=_}}'
		],
		'Template:InlineFormattedTpl_2' => [
			'format' => "\n{{_ | _ = _}}"
		],
		'Template:InlineFormattedTpl_3' => [
			'format' => '{{_| _____ = _}}'
		],
		'Template:BlockFormattedTpl_1' => [
			'format' => "{{_\n| _ = _\n}}"
		],
		'Template:BlockFormattedTpl_2' => [
			'format' => "\n{{_\n| _ = _\n}}\n"
		],
		'Template:BlockFormattedTpl_3' => [
			'format' => "{{_|\n _____ = _}}"
		]
	];

	/** @var string wiki prefix for which we are mocking the api access */
	private $prefix = 'enwiki';

	/**
	 * @param ?string $prefix
	 */
	public function __construct( ?string $prefix = null ) {
		if ( $prefix ) {
			$this->prefix = $prefix;
		}
		// PORT-FIXME: Need to get this value
		// $wtSizeLimit = $parsoidOptions->limits->wt2html->maxWikitextSize;
		$wtSizeLimit = 1000000;
		$mainSlot = &self::$LARGE_PAGE['query']['pages'][0]['revisions'][0]['slots']['main'];
		$mainSlot['content'] = str_repeat( 'a', $wtSizeLimit + 1 );
	}

	/**
	 * Update prefix
	 * @param string $prefix
	 */
	public function setApiPrefix( string $prefix ): void {
		$this->prefix = $prefix;
	}

	/**
	 * @param array $params
	 * @return array
	 */
	public function makeRequest( array $params ): array {
		switch ( $params['action'] ?? null ) {
			case 'query':
				return $this->processQuery( $params );

			case 'parse':
				return $this->parse( $params['text'], !empty( $params['onlypst'] ) );

			case 'templatedata':
				return $this->fetchTemplateData( $params );

			case 'expandtemplates':
				$ret = $this->preProcess( $params['titles'] ?? $params['title'], $params['text'], $params['revid'] ?? null );
				if ( $ret ) {
					$ret = $ret + [
						'categories' => [],
						'modules' => [],
						'modulescripts' => [],
						'modulestyles' => []
					];
				}
				return $ret;

			default:
				return []; // FIXME: Maybe some error
		}
	}

	/**
	 * @param string $filename
	 * @param ?int $twidth
	 * @param ?int $theight
	 * @return ?array
	 */
	private function imageInfo(
		string $filename, ?int $twidth, ?int $theight
	) : ?array {
		$normPagename = self::PNAMES[$filename] ?? $filename;
		$normFilename = self::FNAMES[$filename] ?? $filename;
		$props = self::FILE_PROPS[$normFilename] ?? null;
		if ( $props === null ) {
			// We don't have info for this file
			return null;
		}

		$md5 = md5( $normFilename );
		$md5prefix = $md5[0] . '/' . $md5[0] . $md5[1] . '/';
		$baseurl = self::IMAGE_BASE_URL . '/' . $md5prefix . $normFilename;
		$height = $props['height'];
		$width = $props['width'];
		$turl = self::IMAGE_BASE_URL . '/thumb/' . $md5prefix . $normFilename;
		$durl = self::IMAGE_DESC_URL . '/' . $normFilename;
		$mediatype = $props['mediatype'] ??
			( $props['mime'] === 'image/svg+xml' ? 'DRAWING' : 'BITMAP' );

		$info = [
			'size' => $props['size'],
			'height' => $height,
			'width' => $width,
			'url' => $baseurl,
			'descriptionurl' => $durl,
			'mediatype' => $mediatype,
			'mime' => $props['mime']
		];

		if ( isset( $props['duration'] ) ) {
			$info['duration'] = $props['duration'];
		}

		if ( $mediatype === 'VIDEO' && !$twidth && !$theight ) {
			$twidth = $width;
			$theight = $height;
		}

		if ( $theight || $twidth ) {
			if ( $theight === null ) {
				// File::scaleHeight in PHP
				$theight = round( $height * $twidth / $width );
			} elseif ( $twidth === null ) {
				// MediaHandler::fitBoxWidth in PHP
				// This is crazy!
				$idealWidth = $width * $theight / $height;
				$roundedUp = ceil( $idealWidth );
				if ( round( $roundedUp * $height / $width ) > $theight ) {
					$twidth = floor( $idealWidth );
				} else {
					$twidth = $roundedUp;
				}
			} else {
				if ( round( $height * $twidth / $width ) > $theight ) {
					$twidth = ceil( $width * $theight / $height );
				} else {
					$theight = round( $height * $twidth / $width );
				}
			}

			$urlWidth = $twidth;
			if ( $twidth > $width ) {
				// The PHP api won't enlarge a bitmap ... but the batch api will.
				// But, to match the PHP sections, don't scale.
				if ( $mediatype !== 'DRAWING' ) {
					$urlWidth = $width;
				}
			}
			if ( $urlWidth !== $width || $mediatype === 'AUDIO' || $mediatype === 'VIDEO' ) {
				$turl .= '/' . $urlWidth . 'px-' . $normFilename;
				switch ( $mediatype ) {
					case 'AUDIO':
						// No thumbs are generated for audio
						$turl = self::IMAGE_BASE_URL . '/w/resources/assets/file-type-icons/fileicon-ogg.png';
						break;
					case 'VIDEO':
						$turl .= '.jpg';
						break;
					case 'DRAWING':
						$turl .= '.png';
						break;
				}
			} else {
				$turl = $baseurl;
			}
			$info['thumbwidth'] = $twidth;
			$info['thumbheight'] = $theight;
			$info['thumburl'] = $turl;
		}

		return [
			'result' => $info,
			'normPagename' => $normPagename
		];
	}

	/**
	 * @param array $params
	 * @return array
	 */
	private function processQuery( array $params ): array {
		if ( ( $params['meta'] ?? null ) === 'siteinfo' ) {
			if ( !isset( $this->cachedConfigs[$this->prefix] ) ) {
				$this->cachedConfigs[$this->prefix] = json_decode(
					file_get_contents( __DIR__ . "/../../baseconfig/2/$this->prefix.json" ), true );
			}
			return $this->cachedConfigs[$this->prefix];
		}

		$revid = $params['revids'] ?? null;

		if ( ( $params['prop'] ?? null ) === 'revisions' ) {
			if ( $revid === '1' || $params['titles'] === 'Main_Page' ) {
				return self::$MAIN_PAGE;
			} elseif ( $revid === '2' || $params['titles'] === 'Junk_Page' ) {
				return self::$JUNK_PAGE;
			} elseif ( $revid === '3' || $params['titles'] === 'Large_Page' ) {
				return self::$LARGE_PAGE;
			} elseif ( $revid === '63' || $params['titles'] === 'Revision_ID' ) {
				return self::$REVISION_PAGE;
			} elseif ( $revid === '100' || $params['titles'] === 'Reuse_Page' ) {
				return self::$REUSE_PAGE;
			} elseif ( $revid === '101' || $params['titles'] === 'JSON_Page' ) {
				return self::$JSON_PAGE;
			} elseif ( $revid === '102' || $params['titles'] === 'Lint_Page' ) {
				return self::$LINT_PAGE;
			} elseif ( $revid === '103' || $params['titles'] === 'Redlinks_Page' ) {
				return self::$REDLINKS_PAGE;
			} elseif ( $revid === '104' || $params['titles'] === 'Variant_Page' ) {
				return self::$VARIANT_PAGE;
			} elseif ( $revid === '105' || $params['titles'] === 'No_Variant_Page' ) {
				return self::$NOVARIANT_PAGE;
			} elseif ( $revid === '999' || $params['titles'] === 'Old_Response' ) {
				return self::$OLD_RESPONSE;
			} else {
				return [ 'query' => [ 'pages' => [
							[
								'ns' => 6,
								'title' => json_encode( $params['titles'] ),
								'missing' => '',
								'imagerepository' => ''
							]
						]
					]
				];
			}
		}

		if ( ( $params['prop'] ?? null ) === 'info|pageprops' ) {
			$ret = [];
			$titles = preg_split( '/\|/', $params['titles'] );
			foreach ( $titles as $t ) {
				$props = [ 'title' => $t ];
				if ( in_array( $t, self::$missingTitles, true ) ) {
					$props['missing'] = '';
				}
				if ( in_array( $t, self::$specialTitles, true ) ) {
					$props['special'] = '';
				}
				if ( in_array( $t, self::$redirectTitles, true ) ) {
					$props['redirect'] = '';
				}
				if ( in_array( $t, self::$disambigTitles, true ) ) {
					$props['pageprops'] = [ 'disambiguation' => '' ];
				}
				$ret[] = $props;
			}
			return [ 'query' => [ 'pages' => $ret ] ];
		}

		if ( ( $params['prop'] ?? null ) === 'imageinfo' ) {
			$response = [ 'query' => [] ];
			$filename = $params['titles']; // assumes this is a single file
			$tonum = function ( $x ) {
				return $x ? (int)$x : null;
			};
			$ii = self::imageInfo(
				$filename,
				isset( $params['iiurlwidth'] ) ? $tonum( $params['iiurlwidth'] ) : null,
				isset( $params['iiurlheight'] ) ? $tonum( $params['iiurlheight'] ) : null
			);
			if ( $ii === null ) {
				$p = [
					'ns' => 6,
					'title' => $filename,
					'missing' => '',
					'imagerepository' => '',
					'imageinfo' => [ [
						'size' => 0,
						'width' => 0,
						'height' => 0,
						'filemissing' => '',
						'mime' => null,
						'mediatype' => null
					] ]
				];
				$p['missing'] = $p['imageinfo']['filemissing'] = true;
				$p['badfile'] = false;
			} else {
				if ( $filename !== $ii['normPagename'] ) {
					$response['query']['normalized'] = [
						[ 'from' => $filename, 'to' => $ii['normPagename'] ]
					];
				}
				$p = [
					'pageid' => 1,
					'ns' => 6,
					'title' => $ii['normPagename'],
					'imageinfo' => [ $ii['result'] ]
				];
				$p['badfile'] = false;
			}
			$response['query']['pages'] = [ $p ];

			return $response;
		}

		return [ "error" => new Error( 'Uh oh!' ) ];
	}

	/**
	 * @param string $text
	 * @param bool $onlypst
	 * @return array
	 */
	private function parse( string $text, bool $onlypst ): array {
		// We're performing a subst
		if ( $onlypst ) {
			return [ 'text' => preg_replace( '/\{\{subst:1x\|([^}]+)\}\}/', '$1', $text, 1 ) ];
		}

		$res = null;
		// Render to html the contents of known extension tags
		// These are the only known extensions (besides native extensions)
		// used in parser tests currently. This would need to be updated
		// as more templates are added OR we need to rely on true parsing.
		preg_match( '#<([A-Za-z][^\t\n\v />\0]*)#', $text, $match );
		switch ( $match[1] ?? '' ) {
			// FIXME: this isn't really used by the mocha tests
			// since some mocha tests hit the production db, but
			// when we fix that, they should go through this.
			case 'templatestyles':
				$res = "<style data-mw-deduplicate='TemplateStyles:r123456'>small { font-size: 120% } big { font-size: 80% }</style>"; // Silliness
				break;

			case 'translate':
				$res = $text;
				break;

			case 'indicator':
			case 'section':
				$res = "\n";
				break;

			default:
				throw new Error( 'Unhandled extension type encountered in: ' . $text );
		}

		$parse = [
			'text' => $res,
			'categories' => [],
			'modules' => [],
			'modulescripts' => [],
			'modulestyles' => []
		];
		return [ 'parse' => $parse ];
	}

	/**
	 * @param string $title
	 * @param string $text
	 * @param ?int $revid
	 * @return ?array
	 */
	private function preProcess(
		string $title, string $text, ?int $revid
	): ?array {
		// These are the only known templates in current parser tests.
		// This would need to be updated as more templates are added OR we need
		// to rely on true (instead of mock) preprocessing.
		preg_match( '/{{1x\|(.*?)}}/', $text, $match );
		if ( $match ) {
			return [ 'wikitext' => $match[1] ];
		} elseif ( $text === '{{colours of the rainbow}}' ) {
			return [ 'wikitext' => 'purple' ];
		} elseif ( $text === '{{REVISIONID}}' ) {
			return [ 'wikitext' => (string)$revid ];
		} else {
			error_log( "UNKNOWN TEMPLATE: $text for $title\n" );
			return null;
		}
	}

	/**
	 * @param array $params
	 * @return array
	 */
	private function fetchTemplateData( array $params ): array {
		return [
			// Assumes that titles is a single title
			// (which is how Parsoid uses this)
			'pages' => [
				'1' => self::$templateData[$params['titles'] ?? ''] ?? []
			]
		];
	}
}
