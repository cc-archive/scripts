#!/usr/bin/python

'''A simple script to check if web connections are
working for a web site on this host.  If it seems
to be down then try restarting Varnish and hope 
that that fixes the problem'''

import os
import urllib2

url = 'http://wiki.creativecommons.org/'
cmd = '/etc/init.d/varnish restart'

def restart_varnish():
    print "Restarting Varnish and hoping that helps."
    os.system(cmd)

try:
    varnish_handle = urllib2.urlopen(url)
except e:
    print "Error fetching ", url, "due to", e
    try:
        apache_handle = urllib2.urlopen(url + ':8080')
    except e:
        print "Neither Varnish or Apache is responding."
    else:
        print e
        print "Apache is responding but Varnish isn't."
        restart_varnish()
else:
    # Make sure the document isn't 0 length, which Varnish
    # has been known to return when malfunctioning.
    if len(varnish_handle.read()) == 0:
        print "A zero-length file was returned when fetching", url 
        restart_varnish()
