interface MwApi {
	saveOption( name: string, value: unknown ): JQuery.Promise<any>;
}

type MwApiConstructor = new( options?: Object ) => MwApi;

interface MediaWiki {
	util: {
		/**
		 * Return a wrapper function that is debounced for the given duration.
		 *
		 * When it is first called, a timeout is scheduled. If before the timer
		 * is reached the wrapper is called again, it gets rescheduled for the
		 * same duration from now until it stops being called. The original function
		 * is called from the "tail" of such chain, with the last set of arguments.
		 *
		 * @since 1.34
		 * @param {number} delay Time in milliseconds
		 * @param {Function} callback
		 * @return {Function}
		 */
		debounce(delay: number, callback: Function): () => void;
	};
	Api: MwApiConstructor;
	config: {
		get( configKey: string|null ): string;
	}
}

declare const mw: MediaWiki;
