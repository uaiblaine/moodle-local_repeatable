# validate_pattern(): customfield_pattern is never asked about the configuration.
s~foreach \(\$patternerrors\(pattern::config\(\$column\)\) as \$message\) \{~foreach ([] as \$message) {~s;
