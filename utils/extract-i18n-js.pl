#!/usr/bin/perl -w
#
use strict;

while (<STDIN>) {
	chomp;

	if (/(__|Notify.progress|Notify.msg|Notify.info|Notify.error)\(['"](.*?)['"]\)/) {
		my $msg = $2;

		$msg =~ s/\"/\\\"/g;

		print "print T_js_decl(\"$msg\");\n";
	}
}
