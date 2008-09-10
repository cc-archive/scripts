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
paypal_completed = csv.reader(open(sys.argv[-1]), delimiter=field_delimiter, lineterminator=line_terminator)

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

discrepancy = 0

for record in paypal_completed:
    if record[31]:
        contribution = civicrm_contributions.select(civicrm_contributions.c.invoice_id==record[31]).execute().fetchone()
        if contribution:
            if record[5] == "Completed" and contribution.contribution_status_id != 1:
                print "PayPal says TXN ID " , record[31], " is completed, but not CiviCRM"
                print "Contact ID: ", contribution.contact_id
                print "CiviCRM status: ", contribution.contribution_status_id
                print "."
                discrepancy = discrepancy + 1

print discrepancy, " discrepancies."
