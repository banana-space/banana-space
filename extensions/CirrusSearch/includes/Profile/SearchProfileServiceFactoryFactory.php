<?php

namespace CirrusSearch\Profile;

use CirrusSearch\SearchConfig;

interface SearchProfileServiceFactoryFactory {
	public function getFactory( SearchConfig $config ): SearchProfileServiceFactory;
}
