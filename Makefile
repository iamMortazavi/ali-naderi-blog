# Local development and publishing. VAULT defaults to the value in .env, then to the example vault:
#   make serve VAULT=../content-vault
-include .env
VAULT ?= example-vault
export PYTHONPATH := tools

.PHONY: serve content build test deploy deploy-check telegram-dry-run clean

serve:              ## Live preview at http://localhost:1313 (admin: /admin/); rebuilds when the vault changes
	python3 -m blogtools.dev --vault $(VAULT)

content:            ## Convert the vault into site/content/<lang>/posts
	python3 -m blogtools.build_content --vault $(VAULT)

build: content      ## Production build into site/public
	hugo --source site --gc --minify

test:               ## Unit tests and a full example build
	python3 -m unittest discover -s tools/tests -t tools

deploy-check:       ## Build and list what would be uploaded; uploads nothing
	python3 -m blogtools.deploy --dry-run

deploy:             ## Build and publish to the cPanel host over FTPS, then post new articles to Telegram
	python3 -m blogtools.deploy

telegram-dry-run: content  ## Show what would be sent to Telegram, change nothing
	python3 -m blogtools.telegram --base-url https://example.com --dry-run

clean:
	rm -rf site/public site/resources build site/content/*/posts
