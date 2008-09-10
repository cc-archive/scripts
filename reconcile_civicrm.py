#!/usr/bin/env python

import sys
import logging
import csv  # http://docs.python.org/lib/module-csv.html
import sqlalchemy

##### --------------- SET THESE VALUES CORRECTLY ------------------- #####
field_delimiter = ','
line_terminator = '\n'
##### -------------------------------------------------------------- #####

# parse the file passed in as the last argument
if len(sys.argv) > 1:
    csv_file = sys.argv[-1]
else:
    print "This script takes 1 argument: the path to a PayPal CSV export."
    exit()

paypal_completed = csv.reader(open(csv_file))

# setup database connectivity
db = sqlalchemy.create_engine('mysql://root@localhost/civicrm', convert_unicode=True)
metadata = sqlalchemy.MetaData(db)
civicrm_contributions = sqlalchemy.Table('civicrm_contribution', metadata, autoload=True)

# configure the logger.  see: http://docs.python.org/lib/module-logging.html
logging.basicConfig(
	format='%(levelname)-8s %(message)s',
	filename='reconcile_civicrm.log',
	filemode='w'
)

not_found = 0
discrepancies = 0

for record in paypal_completed:
    if record[31]:
        contribution = civicrm_contributions.select(civicrm_contributions.c.invoice_id == record[31]).execute().fetchone()
        if contribution:
            if record[5] == "Completed" and contribution.contribution_status_id != 1:
                msg = 'DISCREPANCY: ' + unicode(record[3], 'iso-8859-1') + ', invoice# ' + record[31] + ', ' + record[0]
                print msg
                logging.warning(msg)
                discrepancies = discrepancies + 1
        else:
            msg = 'NOT FOUND: ' + unicode(record[3], 'iso-8859-1') + ', invoice# ' + record[31] + ', ' + record[0]
            print msg
            logging.warning(msg)
            not_found = not_found + 1


print '\n'
msg = str(discrepancies) + " discrepancies."
print msg
logging.info(msg)
msg = str(not_found) + " not found."
print msg
logging.info(msg)
