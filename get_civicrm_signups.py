#!/usr/bin/env python

import re
import os
import datetime
import sqlalchemy

PRODUCTION = True

rcpt_to = 'development@creativecommons.org'
date = datetime.date.today().isoformat()
letters = []
events = []

# setup database connectivity
if PRODUCTION:
    db = sqlalchemy.create_engine('mysql://root@localhost/civicrm', convert_unicode=True)
else:
    db = sqlalchemy.create_engine('mysql://root@localhost/civicrm_staging', convert_unicode=True)

metadata = sqlalchemy.MetaData(db)
tbl_email = sqlalchemy.Table('civicrm_email', metadata, autoload=True)
if PRODUCTION:
    tbl_signups = sqlalchemy.Table('civicrm_value_1_communications_6', metadata, autoload=True)
else:
    tbl_signups = sqlalchemy.Table('civicrm_value_1_mailings_6', metadata, autoload=True)

if PRODUCTION:
    c_letter = tbl_signups.c.newletters
    c_events = tbl_signups.c.events
else:
    c_letter = tbl_signups.c.newletter
    c_events = tbl_signups.c.events_new

c_id = tbl_signups.c.id
c_emailid = tbl_email.c.contact_id

signups = tbl_signups.select(sqlalchemy.or_(c_letter == 'yes', c_events == 'yes')).execute().fetchall()

for signup in signups:
    email = tbl_email.select(c_emailid == signup[2]).execute().fetchone()
    if re.search("yes", signup[3]):
        letters.append(email[3])
        if PRODUCTION:
	    tbl_signups.update(c_id == signup[0], {'newletters':''}).execute()
	else:
	    tbl_signups.update(c_id == signup[0], {'newletter':''}).execute()
    if re.search("yes", signup[4]):
        events.append(email[3])
        if PRODUCTION:
	    tbl_signups.update(c_id == signup[0], {'events':''}).execute()
        else:
	    tbl_signups.update(c_id == signup[0], {'events_new':''}).execute()

# If there were any new subscription requests
if letters or events:
    p = os.popen("/usr/sbin/sendmail -t", "w")
    p.write('To: %s\n' % rcpt_to)
    p.write('From: civicrm@support.creativecommons.org\n')
    p.write('Subject: Mail list subscriptions for %s \n' % date)
    p.write('\n')
    p.write('Newsletter subscriptions:\n')
    p.write('\n'.join(letters))
    p.write('\n\n')
    p.write('Events subscriptions:\n')
    p.write('\n'.join(events))
    status = p.close()
