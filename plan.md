# AI HTTP Client Implementation Plan

## Current Status
✅ **Completed:**
- OpenAI provider with Responses API, modular architecture (StreamingModule, FunctionCalling)
- Multi-modal support for images and files
- File upload utilities for large PDFs
- Basic streaming infrastructure
- Request normalization for OpenAI patterns

❌ **Missing Critical Components:**

## Phase 1: Complete Provider Ecosystem (Week 1-2)

### 1.1 Anthropic Provider Modularization
- [ ] Create `src/Providers/Anthropic/StreamingModule.php`
- [ ] Create `src/Providers/Anthropic/FunctionCalling.php`
- [ ] Refactor `src/Providers/Anthropic/Provider.php` to use modules
- [ ] Add multi-modal support to Anthropic RequestNormalizer
- [ ] Update Anthropic ResponseNormalizer for tool calling

### 1.2 Google Gemini Provider (Complete Implementation)
- [ ] Create `src/Providers/Gemini/` directory structure
- [ ] Create `src/Providers/Gemini/Provider.php`
- [ ] Create `src/Providers/Gemini/RequestNormalizer.php`
- [ ] Create `src/Providers/Gemini/ResponseNormalizer.php`
- [ ] Create `src/Providers/Gemini/StreamingModule.php`
- [ ] Create `src/Providers/Gemini/FunctionCalling.php`
- [ ] Add Gemini to provider auto-discovery

### 1.3 Grok/X.AI Provider (Complete Implementation)
- [ ] Create `src/Providers/Grok/` directory structure
- [ ] Create `src/Providers/Grok/Provider.php`
- [ ] Create `src/Providers/Grok/RequestNormalizer.php`
- [ ] Create `src/Providers/Grok/ResponseNormalizer.php`
- [ ] Create `src/Providers/Grok/StreamingModule.php`
- [ ] Create `src/Providers/Grok/FunctionCalling.php`
- [ ] Add Grok to provider auto-discovery

### 1.4 OpenRouter Provider (Complete Implementation)
- [ ] Create `src/Providers/OpenRouter/` directory structure
- [ ] Create `src/Providers/OpenRouter/Provider.php`
- [ ] Create `src/Providers/OpenRouter/RequestNormalizer.php`
- [ ] Create `src/Providers/OpenRouter/ResponseNormalizer.php`
- [ ] Create `src/Providers/OpenRouter/StreamingModule.php`
- [ ] Create `src/Providers/OpenRouter/FunctionCalling.php`
- [ ] Add OpenRouter to provider auto-discovery

## Phase 2: Provider Feature Parity (Week 3)

### 2.1 Multi-Modal Support Across Providers
- [ ] Anthropic: Image URL and file handling in RequestNormalizer
- [ ] Gemini: Vision API integration
- [ ] Grok: Multi-modal capabilities (if supported)
- [ ] OpenRouter: Pass-through multi-modal to underlying providers

### 2.2 Function Calling Standardization
- [ ] Ensure all providers normalize tool schemas correctly
- [ ] Verify tool call extraction from provider-specific responses
- [ ] Test tool result formatting for conversation continuation

### 2.3 Streaming Parity
- [ ] Anthropic: SSE event handling for Claude
- [ ] Gemini: Streaming API integration
- [ ] Grok: Streaming support (if available)
- [ ] OpenRouter: Streaming pass-through

## Phase 3: Core Infrastructure Improvements (Week 4)

### 3.1 Enhanced Provider Registry
- [ ] Auto-discover streaming modules and function calling modules
- [ ] Validate provider completeness during registration
- [ ] Handle loading order dependencies between modules
- [ ] Add provider capability detection (streaming, tools, multi-modal)

### 3.2 Response Normalization Enhancements
- [ ] Standardize error response formats across providers
- [ ] Normalize usage/token counting across providers
- [ ] Handle provider-specific metadata (model versions, etc.)
- [ ] Standardize tool call response formats

### 3.3 Advanced Client Features
- [ ] Provider fallback chains with automatic retry
- [ ] Usage tracking and cost calculation per provider
- [ ] Rate limiting and quota management
- [ ] Request/response caching for development

## Phase 4: Plugin Integration Testing (Week 5)

### 4.1 Wordsurf Integration Test
- [ ] Create test plugin that replaces Wordsurf's OpenAI client
- [ ] Verify streaming SSE compatibility
- [ ] Test tool calling integration
- [ ] Validate frontend EventSource compatibility

### 4.2 Data Machine Integration Test
- [ ] Replace Data Machine's three API classes with AI HTTP Client
- [ ] Test multi-modal image processing
- [ ] Test file upload for large PDFs
- [ ] Verify fact-checking tool calling

### 4.3 Cold Outreach Integration Test
- [ ] Replace simple ChatGPT API with provider abstraction
- [ ] Test email generation workflow
- [ ] Add multi-provider selection UI

### 4.4 bbPress Bot Integration Test
- [ ] Replace ChatGPT_API class
- [ ] Test two-phase AI processing (keyword extraction + response)
- [ ] Verify WordPress integration patterns

## Phase 5: Documentation and Migration Tools (Week 6)

### 5.1 Migration Documentation
- [ ] Create plugin migration guides for each target plugin
- [ ] Document request/response format changes
- [ ] Provide code examples for common patterns

### 5.2 Migration Helper Tools
- [ ] Configuration migration scripts
- [ ] Backward compatibility layers
- [ ] Provider selection UI templates

### 5.3 Testing and Validation
- [ ] Create comprehensive test suite
- [ ] Performance benchmarking vs. direct API calls
- [ ] Memory usage optimization
- [ ] WordPress compatibility testing

## Success Criteria

### Technical Requirements
- [ ] All 4 target plugins can replace their AI API classes with AI HTTP Client
- [ ] No functionality loss during migration
- [ ] Multi-provider support working for all plugins
- [ ] Streaming performance matches or exceeds direct API calls

### Architecture Requirements
- [ ] Modular provider architecture maintained
- [ ] Single responsibility principle followed
- [ ] Auto-discovery working for all components
- [ ] Version conflict resolution working

### Integration Requirements
- [ ] WordPress Action Scheduler-style distribution model
- [ ] Provider management UI component working
- [ ] Error handling compatible with WordPress patterns
- [ ] Security best practices maintained

## Priority Order

**Immediate (This Week):**
1. Complete Anthropic modularization
2. Implement Google Gemini provider
3. Enhance response normalization

**Next (Following Week):**
1. Add Grok and OpenRouter providers
2. Implement provider fallback chains
3. Begin plugin integration testing

**Final:**
1. Documentation and migration tools
2. Performance optimization
3. Comprehensive testing

This plan ensures the AI HTTP Client becomes a true drop-in replacement for all target plugins while providing the multi-provider abstraction needed for universal AI support.