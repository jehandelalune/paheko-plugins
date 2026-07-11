# Makefile pour les tests du plugin HelloAsso

.PHONY: test test-coverage coverage clean

# Couleurs pour l'affichage
GREEN := \033[0;32m
YELLOW := \033[1;33m
RED := \033[0;31m
NC := \033[0m # No Color

# Répertoire courant
TEST_DIR := $(shell pwd)
PLUGIN_DIR := $(shell dirname $(TEST_DIR))

# Commande pour exécuter les tests
test:
	@echo "$(GREEN)===== Execution des tests HelloAsso Order =====$(NC)"
	@cd $(TEST_DIR) && php run.php
	@echo ""

# Commande pour générer le rapport de couverture (HTML)
coverage:
	@echo "$(GREEN)===== Generation du rapport de couverture HTML =====$(NC)"
	@cd $(TEST_DIR) && php -d pcov.enabled=1 -d pcov.directory=$(PLUGIN_DIR)/lib run_with_pcov.php html
	@echo ""

# Commande pour exécuter les tests + couverture
# Par défaut génère le rapport HTML
test-coverage:
	@echo "$(GREEN)===== Tests + Couverture =====$(NC)"
	@cd $(TEST_DIR) && php -d pcov.enabled=1 -d pcov.directory=$(PLUGIN_DIR)/lib run_with_pcov.php html
	@echo ""

# Rapport de couverture JSON
coverage-json:
	@echo "$(GREEN)===== Generation du rapport de couverture JSON =====$(NC)"
	@cd $(TEST_DIR) && php -d pcov.enabled=1 -d pcov.directory=$(PLUGIN_DIR)/lib run_with_pcov.php json
	@echo ""

# Rapport de couverture Clover XML
coverage-clover:
	@echo "$(GREEN)===== Generation du rapport de couverture Clover XML =====$(NC)"
	@cd $(TEST_DIR) && php -d pcov.enabled=1 -d pcov.directory=$(PLUGIN_DIR)/lib run_with_pcov.php clover
	@echo ""

# Rapport de couverture Cobertura XML
coverage-cobertura:
	@echo "$(GREEN)===== Generation du rapport de couverture Cobertura XML =====$(NC)"
	@cd $(TEST_DIR) && php -d pcov.enabled=1 -d pcov.directory=$(PLUGIN_DIR)/lib run_with_pcov.php cobertura
	@echo ""

# Rapport de couverture PHPUnit XML (format proche de JUnit)
coverage-junit:
	@echo "$(GREEN)===== Generation du rapport de couverture PHPUnit XML =====$(NC)"
	@cd $(TEST_DIR) && php -d pcov.enabled=1 -d pcov.directory=$(PLUGIN_DIR)/lib run_with_pcov.php xml
	@echo ""

# Rapport de couverture texte
coverage-text:
	@echo "$(GREEN)===== Generation du rapport de couverture texte =====$(NC)"
	@cd $(TEST_DIR) && php -d pcov.enabled=1 -d pcov.directory=$(PLUGIN_DIR)/lib run_with_pcov.php text
	@echo ""

# Nettoyer les fichiers temporaires
clean:
	@echo "Nettoyage des fichiers temporaires..."
	@rm -f $(TEST_DIR)/coverage_report.md
	@rm -rf $(TEST_DIR)/coverage
	@find $(TEST_DIR) -name "paheko_test_*.sqlite" -delete 2>/dev/null || true
	@echo "Nettoyage terminé"

# Cible par défaut
.PHONY: default
default: test-coverage

# Aide
help:
	@echo "Cibles disponibles:"
	@echo "  make test            - Exécuter uniquement les tests"
	@echo "  make coverage        - Générer le rapport de couverture HTML"
	@echo "  make test-coverage  - Exécuter les tests et générer la couverture HTML (par défaut)"
	@echo "  make coverage-json   - Générer le rapport de couverture au format JSON"
	@echo "  make coverage-clover  - Générer le rapport de couverture au format Clover XML"
	@echo "  make coverage-cobertura - Générer le rapport de couverture au format Cobertura XML"
	@echo "  make coverage-junit  - Générer le rapport de couverture au format JUnit XML"
	@echo "  make coverage-text   - Générer le rapport de couverture au format texte"
	@echo "  make clean           - Nettoyer les fichiers temporaires"
	@echo "  make help            - Afficher cette aide"
