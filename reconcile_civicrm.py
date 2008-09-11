#!/usr/bin/env python

import sys
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
log_file = open('reconcile_civicrm.log', 'w')

# setup database connectivity
db = sqlalchemy.create_engine('mysql://root@localhost/civicrm', convert_unicode=True)
metadata = sqlalchemy.MetaData(db)
civicrm_contributions = sqlalchemy.Table('civicrm_contribution', metadata, autoload=True)

not_found = 0
discrepancies = 0

msg = 'Type,Contact ID,Name,Email,Invoice ID,Date,Amount,Transaction Type,Item Title\n'
print msg
log_file.write(msg)

for record in paypal_completed:
    msg = ''
    if record[31]:
        contribution = civicrm_contributions.select(civicrm_contributions.c.invoice_id == record[31]).execute().fetchone()
        if contribution:
            msg = str(contribution.contact_id) + ',' + unicode(record[3], 'iso-8859-1') + ',' + record[10] + ',' + record[31] + ',' + record[0] + ',' + record[7] + ',' + record[4] + ',' + record[15] + '\n'
            if record[5] == "Completed" and contribution.contribution_status_id != 1:
                print msg
                log_file.write('discrepancy,' + msg)
                discrepancies = discrepancies + 1
        else:
            msg = '?' + ',' + unicode(record[3], 'iso-8859-1') + ',' + record[10] + ',' + record[31] + ',' + record[0] + ',' + record[7] + ',' + record[4] + ',' + record[15] + '\n'
            print msg
            log_file.write('not found,' + msg)
            not_found = not_found + 1

log_file.close()
