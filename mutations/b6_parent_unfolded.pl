# column_code_error(): the parent codes are compared exactly, not folded.
s~dictionaries::fold_code\(\$item\['parentcode'\]\)\n(\s+)=== dictionaries::fold_code\(\$parentcode\)~\$item['parentcode']\n$1=== \$parentcode~s;
