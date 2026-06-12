PLUGIN_SLUG := muguerza-checkout
RELEASES_DIR := releases
VERSION_SCRIPT := ./scripts/bump-plugin-version.sh
WP_CLI_HOME_DIR := $(CURDIR)/.wp-cli
DIST_COMPOSE_FILE := docker-compose.dist.yml
DIST_SERVICE := wpcli-dist
CURRENT_VERSION := $(shell sed -n 's/^ \* Version:[[:space:]]*//p' muguerza-checkout.php | head -n 1 | tr -d '[:space:]')
DIST_ZIP := $(RELEASES_DIR)/$(PLUGIN_SLUG).$(CURRENT_VERSION).zip
WP_PROXY ?= http://localhost

.PHONY: help current-version prepare-wp-cli dist bump set-version release hotreload

help:
	@echo "Available targets:"
	@echo "  make current-version              Show the version from muguerza-checkout.php"
	@echo "  make dist                         Create a plugin zip with a dedicated Docker Compose wpcli service"
	@echo "  make bump                         Bump patch version"
	@echo "  make bump PART=minor              Bump minor version"
	@echo "  make bump PART=major              Bump major version"
	@echo "  make set-version VERSION=1.2.3    Set an explicit version"
	@echo "  make release                      Bump patch version and create dist"
	@echo "  make release VERSION=1.2.3        Set version and create dist"
	@echo "  make hotreload                    Start BrowserSync against WP_PROXY"

current-version:
	@sed -n 's/^ \* Version:[[:space:]]*//p' muguerza-checkout.php | head -n 1

prepare-wp-cli:
	@mkdir -p "$(WP_CLI_HOME_DIR)"
	@chmod +x ./scripts/wpcli-dist.sh

hotreload:
	browser-sync start \
		--proxy "$(WP_PROXY)" \
		--files "assets/css/*.css, **/*.php" \
		--no-open

dist: prepare-wp-cli
	UID=$$(id -u) GID=$$(id -g) docker compose -f $(DIST_COMPOSE_FILE) run --rm $(DIST_SERVICE) ./$(DIST_ZIP)

bump:
	$(VERSION_SCRIPT) $(if $(PART),$(PART),patch)

set-version:
ifndef VERSION
	$(error VERSION is required, for example: make set-version VERSION=1.2.3)
endif
	$(VERSION_SCRIPT) $(VERSION)

release:
ifdef VERSION
	$(MAKE) set-version VERSION=$(VERSION)
else
	$(MAKE) bump PART=$(if $(PART),$(PART),patch)
endif
	$(MAKE) dist
