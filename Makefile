.PHONY: help test test-compiler test-llm test-operations test-media test-router clean install docker-up docker-down

# Default target
help:
	@echo "Pagewright - Development Makefile"
	@echo ""
	@echo "Available targets:"
	@echo "  make test              - Run all tests"
	@echo "  make test-compiler     - Test markdown compiler"
	@echo "  make test-operations   - Test operations engine"
	@echo "  make test-media        - Test media upload"
	@echo "  make test-llm          - Test LLM integration (requires API key)"
	@echo "  make test-router       - Test router and UI (requires Docker)"
	@echo "  make clean             - Clean test artifacts"
	@echo "  make install           - Install dependencies"
	@echo "  make docker-up         - Start Docker containers"
	@echo "  make docker-down       - Stop Docker containers"
	@echo ""

# Run all tests (except router which requires Docker)
test: test-compiler test-operations test-media
	@echo ""
	@echo "✓ All core tests completed"

# Test the markdown compiler
test-compiler:
	@echo "Running compiler tests..."
	@php tests/test-compiler.php

# Test operations engine
test-operations:
	@echo "Running operations tests..."
	@php tests/test-operations.php

# Test media upload functionality
test-media:
	@echo "Running media upload tests..."
	@php tests/test-media.php

# Test LLM integration (requires OpenAI API key)
test-llm:
	@echo "Running LLM integration tests..."
	@if [ -z "$$OPENAI_API_KEY" ]; then \
		echo "⚠️  Warning: OPENAI_API_KEY not set"; \
		echo "Set it in .env or export OPENAI_API_KEY=your-key"; \
		exit 1; \
	fi
	@php tests/test-llm.php

# Test router and UI (requires running Docker container)
test-router:
	@echo "Running router and UI tests..."
	@if ! docker ps | grep -q pagewright-app; then \
		echo "⚠️  Docker container not running"; \
		echo "Run 'make docker-up' first"; \
		exit 1; \
	fi
	@bash tests/test-router-ui.sh

# Clean test artifacts
clean:
	@echo "Cleaning test artifacts..."
	@rm -rf pagewright/pw-log/patches/test_*
	@rm -f pagewright/pw-public/uploads/test_*
	@rm -f pagewright/pw-public/uploads/thumbs/thumb_test_*
	@echo "✓ Cleaned"

# Install dependencies (Parsedown)
install:
	@echo "Installing dependencies..."
	@if [ ! -f "pagewright/pw-admin/libs/vendor/Parsedown.php" ]; then \
		echo "Downloading Parsedown..."; \
		mkdir -p pagewright/pw-admin/libs/vendor; \
		curl -sL https://raw.githubusercontent.com/erusev/parsedown/master/Parsedown.php \
		     -o pagewright/pw-admin/libs/vendor/Parsedown.php; \
		echo "✓ Parsedown installed"; \
	else \
		echo "✓ Parsedown already installed"; \
	fi

# Start Docker containers
docker-up:
	@echo "Starting Docker containers..."
	@docker-compose up -d
	@echo "✓ Docker containers running"
	@echo ""
	@echo "Access the application:"
	@echo "  Frontend: http://localhost:8880/"
	@echo "  Admin:    http://localhost:8880/pw-admin/"

# Stop Docker containers
docker-down:
	@echo "Stopping Docker containers..."
	@docker-compose down
	@echo "✓ Docker containers stopped"

# Full setup for new installations
setup: install docker-up
	@echo ""
	@echo "✓ Setup complete!"
	@echo ""
	@echo "Next steps:"
	@echo "  1. Copy .env.example to .env"
	@echo "  2. Add your OAuth credentials to .env"
	@echo "  3. Visit http://localhost:8880/pw-admin/"
	@echo ""

# Quick test (fast tests only)
quick-test: test-compiler test-operations
	@echo "✓ Quick tests completed"
