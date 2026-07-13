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
                '${ $.provider }:data.allegro.product_id': 'onProductIdChange'
            }
        },

        initialize: function () {
            this._super();
            this.validation = this.validation || {};
            this.validation['allegro-ean'] = true;
            this.validation.max_text_length = 18;

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
                var product = response[0],
                    categoryComponent;

                if (!product || !product.id) {
                    self._showAlert('Error', $t('Could not find a product with the specified EAN.'));
                    return;
                }

                self.source.set('data.allegro.product_id', product.id);
                self.source.set('data.allegro.allegro_product_name', product.name || '');
                categoryComponent = registry.get(self.parentName + '.category');
                if (!categoryComponent || typeof categoryComponent.selectCategory !== 'function') {
                    self._showAlert('Error', $t('Could not initialize the Allegro category field.'));
                    return;
                }

                categoryComponent.selectCategory(product.category);
                self._fillParametersWhenReady(product.parameters || []);
            }).fail(function (xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : $t('An error occurred while searching the Allegro catalog.');

                self._showAlert('Error', message);
            });
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
