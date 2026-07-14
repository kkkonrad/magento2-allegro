define([
    'jquery',
    'Magento_Ui/js/form/element/abstract',
    'mage/storage',
    'Magento_Ui/js/modal/alert',
    'mage/translate',
    'uiRegistry',
    'ko',
    'Macopedia_Allegro/js/allegro_offer/validation/ean'
], function ($, Input, storage, alert, $t, registry, ko) {
    'use strict';

    return Input.extend({
        defaults: {
            listens: {
                '${ $.provider }:data.allegro.product_id': 'onProductIdChange'
            }
        },

        initialize: function () {
            this._super();
            this.validation = this.validation || {};
            this.validation['allegro-ean'] = true;
            this.validation.max_text_length = 18;
            this.catalogPhrase = ko.observable('');
            this.searchResults = ko.observableArray([]);
            this.searching = ko.observable(false);
            this.searchMessage = ko.observable('');

            return this;
        },

        searchProductByEan: function () {
            var self = this,
                url;

            this.source.set('params.invalid', false);
            this.source.trigger('data.validate');
            if (this.source.get('params.invalid')) {
                return;
            }

            url = this.ajaxUrl + '?' + $.param({
                operation: 'search',
                ean: this.value()
            });

            storage.get(url).done(function (response) {
                var product = response[0];

                if (!product || !product.id) {
                    self._showAlert('Error', $t('Could not find a product with the specified EAN.'));
                    return;
                }

                self._applyCatalogProduct(product, product.gtin || self.value());
            }).fail(function (xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : $t('An error occurred while searching the Allegro catalog.');

                self._showAlert('Error', message);
            });
        },

        searchProductByName: function () {
            var self = this,
                phrase = $.trim(this.catalogPhrase()),
                url;

            if (phrase.length < 3) {
                this._showAlert('Error', $t('Enter at least 3 characters of the product name.'));
                return false;
            }

            this.searching(true);
            this.searchResults([]);
            this.searchMessage('');
            url = this.ajaxUrl + '?' + $.param({
                operation: 'searchByName',
                phrase: phrase
            });

            storage.get(url).done(function (response) {
                var results = Array.isArray(response) ? response : [];

                self.searchResults(results);
                self.searchMessage(results.length
                    ? $t('Select the exact product from the Allegro Catalog results.')
                    : $t('No products matching this name were found.'));
            }).fail(function (xhr) {
                self.searchMessage('');
                self._showAlert(
                    'Error',
                    xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : $t('An error occurred while searching the Allegro catalog.')
                );
            }).always(function () {
                self.searching(false);
            });

            return false;
        },

        selectCatalogProduct: function (product) {
            var self = this,
                url;

            if (product.gtin) {
                this._applyCatalogProduct(product, product.gtin);
                return;
            }

            this.searching(true);
            url = this.ajaxUrl + '?' + $.param({
                operation: 'product',
                product_id: product.id
            });
            storage.get(url).done(function (details) {
                if (!details || !details.gtin) {
                    self._showAlert('Information', $t('The selected Allegro product does not contain a GTIN.'));
                    return;
                }
                self._applyCatalogProduct(details, details.gtin);
            }).fail(function (xhr) {
                self._showAlert(
                    'Error',
                    xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : $t('Could not load the selected Allegro product.')
                );
            }).always(function () {
                self.searching(false);
            });
        },

        _applyCatalogProduct: function (product, gtin) {
            var categoryComponent = registry.get(this.parentName + '.category');

            if (!categoryComponent || typeof categoryComponent.selectCategory !== 'function') {
                this._showAlert('Error', $t('Could not initialize the Allegro category field.'));
                return;
            }

            this.value(gtin || '');
            this.source.set('data.allegro.product_id', product.id);
            this.source.set('data.allegro.allegro_product_name', product.name || '');
            categoryComponent.selectCategory(product.category);
            this._fillParametersWhenReady(product.parameters || []);
            this.searchResults([]);
            this.searchMessage($t('The selected product and GTIN were applied to the offer.'));
        },

        productImage: function (product) {
            var image = product && product.images && product.images.length ? product.images[0] : null;

            return typeof image === 'string' ? image : (image && image.url ? image.url : '');
        },

        handleCatalogSearchKey: function (data, event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                this.searchProductByName();
                return false;
            }
            return true;
        },

        _fillParametersWhenReady: function (productParameters) {
            var self = this,
                parametersComponent = registry.get(this.parentName + '.parameters'),
                parameterMap = {};

            if (!parametersComponent || typeof parametersComponent.whenLoaded !== 'function') {
                this._showAlert('Warning', $t('Parameters could not be loaded automatically.'));
                return;
            }

            $.each(productParameters, function (index, parameter) {
                var normalized = self._normalizeParameter(parameter);

                if (normalized && normalized.id) {
                    parameterMap[normalized.id] = normalized;
                }
            });

            parametersComponent.whenLoaded(function (attributes) {
                var filledCount = 0;

                $.each(attributes, function (index, attribute) {
                    var productParameter = parameterMap[attribute.definition.id];

                    if (productParameter) {
                        self._fillSingleParameter(attribute, productParameter);
                        filledCount++;
                    }
                });

                self._showAlert(
                    filledCount > 0 ? 'Success' : 'Info',
                    filledCount > 0
                        ? $t('The product and its catalog parameters were loaded successfully.')
                        : $t('The product was found. Verify the required category parameters manually.')
                );
            });
        },

        _normalizeParameter: function (parameter) {
            if (typeof parameter !== 'string') {
                return parameter;
            }

            try {
                return JSON.parse(parameter);
            } catch (error) {
                return null;
            }
        },

        _fillSingleParameter: function (attribute, productParameter) {
            if (productParameter.rangeValue && attribute.inputValueMin && attribute.inputValueMax) {
                attribute.inputValueMin(productParameter.rangeValue.from || '');
                attribute.inputValueMax(productParameter.rangeValue.to || '');
            } else if (productParameter.valuesIds && attribute.inputValue) {
                attribute.inputValue(Array.isArray(productParameter.valuesIds)
                    ? productParameter.valuesIds
                    : [productParameter.valuesIds]);
            } else if (productParameter.values && attribute.inputValue) {
                attribute.inputValue([]);
                $.each(productParameter.values, function (index, value) {
                    attribute.addNextValue(value);
                });
            }
        },

        _showAlert: function (title, content) {
            alert({
                title: $t(title),
                content: content
            });
        },

        onProductIdChange: function (value) {
            if (!value) {
                this.source.set('data.allegro.allegro_product_name', '');
            }
        }
    });
});
