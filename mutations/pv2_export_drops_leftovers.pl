# set_exporter::export(): values under no current column are not exported.
s~if \(!isset\(\$read\[\$name\]\)\) \{~if (false) {~s;
