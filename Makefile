

test:
	vendor/bin/phpunit

test-commonmark:
	test -d cmm || git clone https://github.com/jgm/CommonMark cmm
	python cmm/test/spec_tests.py --spec cmm/spec.txt --program "./bin/markdown --flavor=common"

