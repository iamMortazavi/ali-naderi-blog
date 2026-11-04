# Local development. VAULT defaults to the example vault; point it at the real one with
#   make serve VAULT=../content-vault
VAULT ?= example-vault
export PYTHONPATH := tools

.PHONY: content serve build test telegram-dry-run clean

content:            ## Convert the vault into site/content/<lang>/posts
	python3 -m blogtools.build_content --vault $(VAULT)

serve: content      ## Live preview at http://localhost:1313
	hugo server --source site --disableFastRender

build: content      ## Production build into site/public
	hugo --source site --gc --minify

test:               ## Unit tests and a full example build
	python3 -m unittest discover -s tools/tests -t tools

telegram-dry-run: content  ## Show what would be sent to Telegram, change nothing
	python3 -m blogtools.telegram --base-url https://example.com --dry-run

clean:
	rm -rf site/public site/resources build site/content/*/posts
