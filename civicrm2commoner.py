#!/usr/bin/env python

"""Script for pulling contributions out of the CiviCRM database
that have a status of completed and are between some arbitary
point in the past and the moment the script is run.
"""

import sys
import time
import datetime
import sqlalchemy

if len(sys.argv) > 1:
    date_in = sys.argv[-1]
    if len(date_in) == 8:
        year = int(date_in[:4])
        month = int(date_in[4:6])
        day = int(date_in[6:])
    else:
        print "You entered an invalid date."
        exit()
    try:
        get_date = datetime.date(year,month,day)
    except Exception:
        print date_in, "is apparently not a valid date."
        exit()
else:
    get_date = datetime.date.fromtimestamp(time.time() - 86400)

# setup database connectivity
db = sqlalchemy.create_engine('mysql://root@localhost/civicrm', convert_unicode=True)
metadata = sqlalchemy.MetaData(db)
crm_contribs = sqlalchemy.Table('civicrm_contribution', metadata, autoload=True)

recv_date = crm_contribs.c.receive_date
status = crm_contribs.c.contribution_status_id

contributions = crm_contribs.select(sqlalchemy.and_(recv_date > get_date, status == 1)).execute().fetchall() 

if len(contributions):
    for contribution in contributions:
        print contribution
else:
    return None
