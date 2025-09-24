/**
 * AI HTTP Client - Provider Manager Component JavaScript
 * 
 * Handles all functionality for the AI Provider Manager component
 * including provider selection, model loading, and settings saving.
 */
(function($) {
    'use strict';

    window.AIHttpProviderManager = window.AIHttpProviderManager || {
        instances: {},
        modelCache: new Map(),
        
        init: function(componentId, config) {
            if (this.instances[componentId]) {
                return;
            }
            
            this.instances[componentId] = {
                id: componentId,
                config: config,
                elements: this.getElements(componentId)
            };
            
            this.bindEvents(componentId);
            
            const elements = this.instances[componentId].elements;
            if (elements.providerSelect && elements.apiKeyInput) {
                const provider = elements.providerSelect.value;
                const apiKey = elements.apiKeyInput.value;
                if (provider && apiKey) {
                    this.fetchModels(componentId, provider);
                }
            }
        },
        
        getElements: function(componentId) {
            return {
                component: document.getElementById(componentId),
                providerSelect: document.getElementById(componentId + '_provider'),
                modelSelect: document.getElementById(componentId + '_model'),
                apiKeyInput: document.getElementById(componentId + '_api_key'),
                saveResult: document.getElementById(componentId + '_save_result'),
                testResult: document.getElementById(componentId + '_test_result'),
                providerStatus: document.getElementById(componentId + '_provider_status')
            };
        },
        
        bindEvents: function(componentId) {
            const elements = this.instances[componentId].elements;
            
            if (elements.providerSelect) {
                elements.providerSelect.addEventListener('change', (e) => {
                    this.onProviderChange(componentId, e.target.value);
                });
            }

            if (elements.apiKeyInput) {
                elements.apiKeyInput.addEventListener('input', (e) => {
                    this.onApiKeyChange(componentId, e.target.value);
                });
            }
            
        },
        
        onProviderChange: function(componentId, provider) {
            const elements = this.instances[componentId].elements;
            
            
            this.loadProviderSettings(componentId, provider)
                .then(() => {
                    this.fetchModels(componentId);
                })
                .catch(error => {
                    this.fetchModels(componentId);
                });
        },
        
        onApiKeyChange: function(componentId, apiKey) {
            const instance = this.instances[componentId];
            if (!instance) {
                return;
            }
            
            clearTimeout(instance.apiKeyTimeout);
            
            instance.apiKeyTimeout = setTimeout(() => {
                const elements = instance.elements;
                const selectedProvider = elements.providerSelect ? elements.providerSelect.value : '';
                const apiKey = elements.apiKeyInput ? elements.apiKeyInput.value.trim() : '';

                if (selectedProvider && apiKey) {
                    this.saveApiKey(componentId, selectedProvider, apiKey)
                        .then(() => {
                            this.fetchModels(componentId);
                        })
                        .catch(error => {
                            // Failed to save API key
                        });
                } else if (!apiKey) {
                    this.fetchModels(componentId);
                }
            }, 500);
        },
        
        fetchModels: function(componentId, provider = null) {
            const elements = this.instances[componentId].elements;
            const config = this.instances[componentId].config;

            if (!elements.modelSelect) return;

            if (!provider) {
                provider = elements.providerSelect ? elements.providerSelect.value : '';
            }

            const apiKey = elements.apiKeyInput ? elements.apiKeyInput.value.trim() : '';

            if (!provider || !apiKey) {
                elements.modelSelect.innerHTML = '<option value="">Select provider and enter API key first</option>';
                return;
            }

            const cacheKey = `ai_models_${provider}_${this.hashApiKey(apiKey)}`;
            const cachedModels = this.modelCache.get(cacheKey);

            if (cachedModels) {
                this.populateModelSelect(elements.modelSelect, cachedModels);
                return;
            }

            elements.modelSelect.innerHTML = '<option value="">Loading models...</option>';

            const requestBody = new URLSearchParams({
                action: 'ai_http_get_models',
                provider: provider,
                nonce: config.nonce
            });

            fetch(config.ajax_url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: requestBody
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.modelCache.set(cacheKey, {
                        success: true,
                        data: data.data
                    });

                    this.populateModelSelect(elements.modelSelect, {
                        success: true,
                        data: data.data
                    });
                } else {
                    const errorMessage = data.data || 'Error loading models';
                    elements.modelSelect.innerHTML = `<option value="">${errorMessage}</option>`;
                }
            })
            .catch(error => {
                elements.modelSelect.innerHTML = '<option value="">Connection error</option>';
            });
        },

        populateModelSelect: function(selectElement, response) {
            if (response.success) {
                selectElement.innerHTML = '';
                const selectedModel = selectElement.getAttribute('data-selected-model') || '';

                Object.entries(response.data).forEach(([key, value]) => {
                    const option = document.createElement('option');
                    option.value = key;
                    option.textContent = typeof value === 'object' ?
                        (value.name || value.id || key) :
                        value;
                    option.selected = (key === selectedModel);
                    selectElement.appendChild(option);
                });
            }
        },

        hashApiKey: function(apiKey) {
            if (!apiKey) return 'nokey';

            // Simple hash for session-based caching
            let hash = 0;
            for (let i = 0; i < apiKey.length; i++) {
                const char = apiKey.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash; // Convert to 32-bit integer
            }
            return Math.abs(hash).toString(16).padStart(8, '0').substr(0, 8);
        },
        
        saveApiKey: function(componentId, provider, apiKey) {
            const instance = this.instances[componentId];
            const config = instance.config;
            
            const requestBody = new URLSearchParams({
                action: 'ai_http_save_api_key',
                provider: provider,
                api_key: apiKey,
                nonce: config.nonce
            });
            
            return fetch(config.ajax_url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: requestBody
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.data?.message || 'Failed to save API key');
                }
                return data;
            });
        },
        
        
        loadProviderSettings: function(componentId, provider) {
            const instance = this.instances[componentId];
            if (!instance) {
                return Promise.reject('Component not initialized');
            }
            
            const elements = instance.elements;
            const config = instance.config;
            
            const requestBody = new URLSearchParams({
                action: 'ai_http_load_provider_settings',
                provider: provider,
                nonce: config.nonce
            });
            
            return fetch(config.ajax_url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: requestBody
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const settings = data.data;
                    
                    if (elements.apiKeyInput) {
                        elements.apiKeyInput.value = settings.api_key || '';
                    }
                    
                    if (elements.modelSelect) {
                        const modelValue = typeof settings.model === 'object' ?
                            (settings.model.id || settings.model.value || '') :
                            (settings.model || '');

                        elements.modelSelect.setAttribute('data-selected-model', modelValue);
                        if (modelValue) {
                            elements.modelSelect.value = modelValue;
                        }
                    }
                    
                    
                    if (elements.instructionsTextarea) {
                        elements.instructionsTextarea.value = settings.instructions || '';
                    }
                    
                    this.updateProviderStatus(componentId, settings.api_key);
                    Object.keys(settings).forEach(key => {
                        if (key.startsWith('custom_')) {
                            const customInput = document.getElementById(componentId + '_' + key);
                            if (customInput) {
                                customInput.value = settings[key] || '';
                            }
                        }
                    });
                    
                    return data;
                } else {
                    throw new Error(data.message || 'Failed to load provider settings');
                }
            })
            .catch(error => {
                throw error;
            });
        },
        
        updateProviderStatus: function(componentId, apiKey = null) {
            const elements = this.instances[componentId].elements;
            
            if (elements.providerStatus) {
                if (apiKey === null && elements.apiKeyInput) {
                    apiKey = elements.apiKeyInput.value;
                }
                
                if (apiKey && apiKey.trim()) {
                    elements.providerStatus.innerHTML = '<span class="ai-provider-status--configured">Configured</span>';
                } else {
                    elements.providerStatus.innerHTML = '<span class="ai-provider-status--not-configured">Not configured</span>';
                }
            }
        }
    };

})(jQuery);