define([
    'jquery',
    'knockout',
    'Macopedia_Allegro/js/allegro_offer/form/field/attributes-table/abstract-attribute',
], function ($, ko, abstract) {
    'use strict';

    return abstract.extend({

        defaults: {
            template: 'Macopedia_Allegro/allegro_offer/form/field/attributes-table/values-ids',
        },

        initialize: function() {
            this.inputValue = ko.observable("");
            this._super();
        },

        initializeValue: function (value) {
            if (value === undefined || value === null) {
                return;
            }
            
            // Handle array of values
            if (Array.isArray(value)) {
                if (value.length === 0) {
                    return;
                }
                if (this.hasRestriction('multipleChoices') && this.getRestrictionValue('multipleChoices')) {
                    this.inputValue(value);
                } else {
                    this.inputValue(value[0]);
                }
                return;
            }
            
            // Handle single value
            if (this.hasRestriction('multipleChoices') && this.getRestrictionValue('multipleChoices')) {
                this.inputValue([value]);
            } else {
                this.inputValue(value);
            }
        },

        _computedValue: function () {
            var val = this.inputValue();

            if (val === "" || val === undefined) {
                return [];
            }
            if (this.hasRestriction('multipleChoices') && this.getRestrictionValue('multipleChoices')) {
                return val;
            }
            return [val];
        }

    });

});