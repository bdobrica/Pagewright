<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/libs/security/Session.php';
require_once __DIR__ . '/libs/util/Http.php';
require_once __DIR__ . '/libs/util/Storage.php';
require_once __DIR__ . '/libs/util/RateLimiter.php';
require_once __DIR__ . '/libs/util/Logger.php';
require_once __DIR__ . '/libs/util/HttpClient.php';
require_once __DIR__ . '/libs/oauth/OAuthProvider.php';
require_once __DIR__ . '/libs/oauth/GoogleProvider.php';
require_once __DIR__ . '/libs/oauth/GitHubProvider.php';
require_once __DIR__ . '/libs/oauth/OAuthManager.php';

// Validators
require_once __DIR__ . '/libs/validators/PageValidator.php';
require_once __DIR__ . '/libs/validators/NavValidator.php';
require_once __DIR__ . '/libs/validators/ThemeValidator.php';

// Content Management
require_once __DIR__ . '/libs/content/ContentManager.php';
require_once __DIR__ . '/libs/content/ThemeManager.php';

// Compiler
require_once __DIR__ . '/libs/vendor/Parsedown.php';
require_once __DIR__ . '/libs/compiler/MarkdownParser.php';
require_once __DIR__ . '/libs/compiler/ComponentParser.php';
require_once __DIR__ . '/libs/compiler/ComponentRegistry.php';
require_once __DIR__ . '/libs/compiler/Compiler.php';
require_once __DIR__ . '/libs/compiler/Publisher.php';

// Operations
require_once __DIR__ . '/libs/operations/Operation.php';
require_once __DIR__ . '/libs/operations/ChangeSet.php';
require_once __DIR__ . '/libs/operations/ChangeLogger.php';
require_once __DIR__ . '/libs/operations/OperationsEngine.php';

// LLM Integration
require_once __DIR__ . '/libs/llm/LLMClient.php';
require_once __DIR__ . '/libs/llm/OpenAIClient.php';
require_once __DIR__ . '/libs/llm/LLMFactory.php';
require_once __DIR__ . '/libs/llm/PromptBuilder.php';
require_once __DIR__ . '/libs/llm/OutputValidator.php';
require_once __DIR__ . '/libs/llm/EditWorkflow.php';