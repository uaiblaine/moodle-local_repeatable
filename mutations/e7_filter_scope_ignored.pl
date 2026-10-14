# The name-filter options ignore the adapter's scope condition.
s~\$scopesql = trim\(\$scopesql\) === '' \? '' : " AND \(\{\$scopesql\}\)";~\$scopesql = '';~s;
