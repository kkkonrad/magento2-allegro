define([
    'Magento_Ui/js/form/element/abstract',
    'mage/storage',
    'Magento_Ui/js/modal/alert',
    'mage/translate',
    'uiRegistry',
    'Macopedia_Allegro/js/allegro_offer/validation/ean'
], function (Input, storage, alert, $t, registry) {
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
                    } else {
                        console.log('Category component not found at path:', self.parentName + '.category');
                    }
                   
                    console.log('Product found and category set:', response[0].category);
                    console.log(self.source);
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

        onProductIdChange: function (value) {
            if (!value) {
                this.source.set('data.allegro_product_name', '');
            }
        }
    });
});
