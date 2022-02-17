 #!/usr/bin/env python

import calendar
import time
import sys
import random

from multiprocessing import Process, Queue
from queue import Full
from urllib.parse import unquote
from urllib.request import urlopen


def send_line(search, destination):
    # Since requests come in with timestamp resolution we assume they came in
    # at some random point in the second
    time.sleep(random.uniform(0, 1))
    start = time.time()
    params = "fulltext=Search&srbackend=CirrusSearch"
    url = "%s/%s?%s" % (destination, search, params)
    urlopen(url)
    print('Fetched ({:07.3f}) {}'.format(time.time() - start, url))


def hostname(wiki):
    wiki = wiki.split(":")[1]
    if wiki == "commonswiki":
        return "commons.wikimedia.org"
    if wiki[2:] == "wiki":
        return wiki[0:2] + ".wikipedia.org"
    # Not perfect but decent
    return wiki[0:2] + "." + wiki[2:] + ".org"


def send_lines(percent, jobs, destination):
    queue = Queue(jobs)  # Only allow a backlog of one per job

    # Spawn jobs.  Note that we just spawn them as daemon because we don't
    # want to bother signaling them when the main process is done and we don't
    # care if they die when it finishes either.  In fact, we'd love for them
    # to die immediately because we want to stop sending requests when the main
    # process stops.
    def work(queue):
        while True:
            try:
                (hostname, search) = queue.get()
                if "%s" in destination:
                    resolved_destination = destination % hostname
                else:
                    resolved_destination = destination
                if hostname == "commons.wikimedia.org":
                    search = "File:" + search
                send_line(search, resolved_destination)
            except (KeyboardInterrupt, SystemExit):
                break
            except:
                continue
    for i in range(jobs):
        p = Process(target=work, args=(queue,))
        p.daemon = True
        p.start()

    # Got to read stdin line by line even on old pythons....
    line = sys.stdin.readline()
    target_lag = None
    while line:
        if random.uniform(0, 100) > percent:
            line = sys.stdin.readline()
            continue
        s = line.strip().split("\t")
        target_time = calendar.timegm(
            time.strptime(s[1][:-1] + "UTC", "%Y-%m-%dT%H:%M:%S%Z"))
        lag = time.time() - target_time
        if target_lag is None:
            target_lag = time.time() - target_time
        wait_time = target_lag - lag
        if wait_time >= 0:
            print('Sleeping {} to stay {} ahead of the logged time.'
                  .format(wait_time, target_lag))
            time.sleep(wait_time)
        try:
            queue.put((hostname(s[2]), unquote(s[3])), False)
        except Full:
            print("Couldn't keep up so dropping the request")
        line = sys.stdin.readline()


if __name__ == "__main__":
    from optparse import OptionParser
    parser = OptionParser(usage="usage: %prog [options] destination")
    parser.add_option("-p", dest="percent", type="int", default=1, metavar="N",
                      help="send this percent of search results")
    parser.add_option("-j", "--jobs", type="int", default=1, metavar="JOBS",
                      help="number of processes used to send searches")
    parser.add_option("-d", "--destination", dest="destination", type="string",
                      metavar="DESTINATION",
                      default="http://127.0.0.1:8080/wiki/Special:Search",
                      help="Where to send the searches.  Add %s as hostname " +
                           "to send to hostname based the log line.")
    (options, args) = parser.parse_args()
    try:
        send_lines(options.percent, options.jobs, options.destination)
    except KeyboardInterrupt:
        pass  # This is how we expect to exit anyway
