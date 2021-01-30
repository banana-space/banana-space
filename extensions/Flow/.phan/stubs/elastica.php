<?php

namespace Elastica {
	class Script {
		/**
		 * @param string      $script
		 * @param array|null  $params
		 * @param string|null $lang
		 */
		public function __construct($script, array $params = null, $lang = null, $id = null) {
		}
		/**
		 * @return string
		 */
		public function getScript() {
		}
	}
}
namespace Elastica\Filter {
	abstract class AbstractFilter {
	}
	class BoolFilter extends AbstractFilter {
		/**
		 * @param array|AbstractFilter $args Filter data
		 * @return $this
		 */
		public function addMust($args){
		}
		/**
		 * @return array Filter array
		 */
		public function toArray(){
		}
	}
	class Terms extends AbstractFilter {
		/**
		* @param string $key   Terms key
		* @param array  $terms Terms values
		*/
		public function __construct($key = '', array $terms = []){
		}
	}
}