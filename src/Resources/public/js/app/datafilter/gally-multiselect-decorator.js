
define(function(require) {
    'use strict';

    const _ = require('underscore');
    const $ = require('jquery');
    const FrontendMultiSelectDecorator = require('orofrontend/js/app/datafilter/frontend-multiselect-decorator');

    /**
     * Gally multiselect decorator: extends the frontend multiselect decorator
     * to add a "show more" button in the dropdown footer
     *
     * @export  gallyplugin/js/app/datafilter/gally-multiselect-decorator
     * @class   gallyplugin.datafilter.GallyMultiSelectDecorator
     * @extends orofrontend.datafilter.FrontendMultiSelectDecorator
     */
    const GallyMultiSelectDecorator = function(options) {
        const params = _.pick(options.parameters, ['showMoreButton']);

        if (!_.isEmpty(params)) {
            this.parameters = _.extend({}, this.parameters, params);
        }

        FrontendMultiSelectDecorator.call(this, options);
    };

    GallyMultiSelectDecorator.prototype = _.extend(
        Object.create(FrontendMultiSelectDecorator.prototype),
        {
            constructor: GallyMultiSelectDecorator,

            setDropdownFooterDesign: function(widget, instance) {
                instance.footer = $('<div />', {
                    'class': 'datagrid-manager__footer'
                });

                if (this.parameters.showMoreButton) {
                    this.setDesignForShowMoreButton(widget, instance);
                }

                if (this.parameters.resetButton) {
                    this.setDesignForResetButton(widget, instance);
                }

                this.setDesignForFooter(widget, instance);
            },

            setDesignForShowMoreButton: function(widget, instance) {
                instance.showMoreButton = $('<button />', {
                    'class': 'btn btn--flat',
                    ...this.parameters.showMoreButton.attr || {}
                }).text(this.parameters.showMoreButton.label || 'Show More');

                instance.showMoreButton.on('click', event => {
                    event.preventDefault();
                    if (typeof this.parameters.showMoreButton.onClick === 'function') {
                        this.parameters.showMoreButton.onClick(event);
                    }
                });

                instance.footer.append(instance.showMoreButton);
            },

            toggleVisibilityShowMoreButton: function(hidden) {
                const instance = this.multiselect('instance');
                if (instance.showMoreButton) {
                    instance.showMoreButton.toggleClass('hidden', hidden);
                }
            }
        }
    );

    return GallyMultiSelectDecorator;
});
