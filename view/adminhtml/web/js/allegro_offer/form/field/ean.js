define([
    'jquery',
    'Magento_Ui/js/form/element/abstract',
    'mage/storage',
    'Magento_Ui/js/modal/alert',
    'mage/translate',
    'uiRegistry',
    'Macopedia_Allegro/js/allegro_offer/validation/ean'
], function ($, Input, storage, alert, $t, registry) {
    'use strict';

    return Input.extend({
        defaults: {
            listens: {
                '${ $.provider }:data.product_id': 'onProductIdChange'
            }
        },

        initialize: function () {
            this._super();
            this.validation = this.validation || {};
            this.validation['allegro-ean'] = true;
            this.validation['max_text_length'] = 18;

            return this;
        },

        searchProductByEan: function () {
            console.log('searchProductByEan');
            var self = this;
            this.source.set('params.invalid', false);
            this.source.trigger('data.validate');
            console.log(this.source.get('data.allegro'));
            storage.get(
                '/rest/V1/allegro/offer/search-product?ean=' + this.value()
            ).done(function (response) {
                
                if (response[0] && response[0].id) {
                    self.source.set('data.allegro.product_id', response[0].id);
                    self.source.set('data.allegro.allegro_product_name', response[0].name);
                   
                    // Trigger category field update to automatically load attributes
                    var categoryComponent = registry.get(self.parentName + '.category');
                   
                    if (categoryComponent) {
                        // Set the initialValue and trigger the category loading process
                        categoryComponent.initialValue = response[0].category;
                        categoryComponent._initializeValue();
                        console.log('Category component initialized with value:', response[0].category);
                        
                        // Wait for parameters to be loaded and then fill them
                        if (response[0].parameters && response[0].parameters.length > 0) {
                            self._waitForParametersAndFill(response[0].parameters);
                        }
                    } else {
                        console.log('Category component not found at path:', self.parentName + '.category');
                    }
                   
                    console.log('Product found and category set:', response[0].category);
                    console.log('Product parameters:', response[0].parameters);
                    console.log(self.source);
                    
                    // Show success message
                    if (response[0].parameters && response[0].parameters.length > 0) {
                        alert({
                            title: $t('Success'),
                            content: $t('Product found! Parameters will be automatically filled once category attributes are loaded.')
                        });
                    } else {
                        alert({
                            title: $t('Success'),
                            content: $t('Product found! Category has been automatically set.')
                        });
                    }
                } else {
                    alert({
                        title: $t('Error'),
                        content: response.message || $t('Could not find product with specified EAN.')
                    });
                }
            }).fail(function () {
                alert({
                    title: $t('Error'),
                    content: $t('An error occurred while searching for the product.')
                });
            });
        },

        /**
         * Wait for parameters to be loaded and then fill them
         * @param {Array} productParameters - Parameters from Allegro product
         */
        _waitForParametersAndFill: function (productParameters) {
            var self = this;
            
            // Wait for parameters table to be loaded
            var parametersComponent = registry.get(self.parentName + '.parameters');
            
            if (!parametersComponent) {
                console.log('Parameters component not found');
                return;
            }

            console.log('Parameters component found:', parametersComponent);
            console.log('Parameters component attributesLoaded:', parametersComponent.attributesLoaded());
            console.log('Parameters component attributes length:', parametersComponent.attributes().length);

            console.log('productParameters',productParameters);

            // Create a map of parameter ID to parameter value for quick lookup
            var parameterMap = {};
            $.each(productParameters, function(index, param) {
                param =  JSON.parse(param);
                parameterMap[param.id] = param;
            });

            console.log('Parameter map created:', parameterMap);

            // Function to wait for parameters to be loaded and then fill them
            var waitForParametersAndFill = function(attempts) {
                attempts = attempts || 0;
                var maxAttempts = 50; // 10 seconds max (50 * 200ms)
                
                if (attempts >= maxAttempts) {
                    console.log('Timeout waiting for parameters to be loaded');
                    alert({
                        title: $t('Warning'),
                        content: $t('Parameters could not be automatically filled. Please fill them manually.')
                    });
                    return;
                }
                
                console.log('Checking parameters component state - attempt ' + (attempts + 1));
                console.log('attributesLoaded:', parametersComponent.attributesLoaded());
                console.log('attributes length:', parametersComponent.attributes().length);
                
                if (parametersComponent.attributesLoaded() && parametersComponent.attributes().length > 0) {
                    console.log('Parameters loaded, filling parameters...');
                    
                    var filledCount = 0;
                    $.each(parametersComponent.attributes(), function(index, attribute) {
                        var paramId = attribute.definition.id;
                        var productParam = parameterMap[paramId];
                        
                        if (productParam) {
                            console.log('Filling parameter', paramId, 'with value:', productParam);
                            self._fillSingleParameter(attribute, productParam);
                            filledCount++;
                        } else {
                            // Check if parameter is required but not found in product data
                            if (attribute.definition.required) {
                                console.log('Required parameter not found in product data:', paramId);
                            }
                        }
                    });
                    
                    // Show success message after parameters are filled
                    if (filledCount > 0) {
                        alert({
                            title: $t('Success'),
                            content: $t('Parameters have been automatically filled with values from Allegro product.')
                        });
                    } else {
                        console.log('No parameters were filled');
                        alert({
                            title: $t('Info'),
                            content: $t('No parameters were automatically filled. Please check if the product has the required parameters.')
                        });
                    }
                } else {
                    // Wait a bit more and try again
                    console.log('Waiting for parameters to be loaded... (attempt ' + (attempts + 1) + ')');
                    setTimeout(function() {
                        waitForParametersAndFill(attempts + 1);
                    }, 200);
                }
            };

            // Start the process
            waitForParametersAndFill();
        },

        /**
         * Fill a single parameter with value from Allegro product
         * @param {Object} attribute - The attribute component
         * @param {Object} productParam - Parameter data from Allegro product
         */
        _fillSingleParameter: function (attribute, productParam) {
            console.log('Filling parameter', productParam.id, 'with value:', productParam);

            // Handle different parameter types
            if (productParam.rangeValue) {
                // Range parameter
                if (attribute.inputValueMin && attribute.inputValueMax) {
                    attribute.inputValueMin(productParam.rangeValue.from || '');
                    attribute.inputValueMax(productParam.rangeValue.to || '');
                    console.log('Range parameter filled:', productParam.rangeValue);
                }
            } else if (productParam.valuesIds) {
                // Values IDs parameter
                if (attribute.inputValue) {
                    if (Array.isArray(productParam.valuesIds)) {
                        attribute.inputValue(productParam.valuesIds);
                    } else {
                        attribute.inputValue([productParam.valuesIds]);
                    }
                    console.log('Values IDs parameter filled:', productParam.valuesIds);
                }
            } else if (productParam.values) {
                // Values parameter
                if (attribute.inputValue) {
                    // Clear existing values
                    attribute.inputValue([]);
                    
                    // Add new values
                    $.each(productParam.values, function(index, value) {
                        attribute.addNextValue(value);
                    });
                    console.log('Values parameter filled:', productParam.values);
                }
            } else {
                console.log('No value found for parameter:', productParam.id);
            }
        },

        onProductIdChange: function (value) {
            if (!value) {
                this.source.set('data.allegro_product_name', '');
            }
        }
    });
});
