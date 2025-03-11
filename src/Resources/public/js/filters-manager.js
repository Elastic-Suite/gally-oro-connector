define(function(require, exports, module) {
    'use strict';

    const _ = require('underscore');
    const BaseFilterManager = require('orofilter/js/filters-manager');
    const mediator = require('oroui/js/mediator');

    /**
     * View that represents all grid filters
     *
     * @export  orofilter/js/filters-manager
     * @class   orofilter.FiltersManager
     * @extends BaseView
     *
     * @event updateList    on update of filter list
     * @event updateFilter  on update data of specific filter
     * @event disableFilter on disable specific filter
     */
    const GallyFilterManager = BaseFilterManager.extend({

        /**
         * Initialize filter list options
         *
         * @param {Object} options
         */
        initialize: function(options) {
            GallyFilterManager.__super__.initialize.call(this, options);
            this.listenTo(mediator, 'datagrid:call_with_collection', this.callWithCollection);
        },

        /**
         * @param {orodatagrid.datagrid.Grid} grid
         */
        updateFilters: function(grid) {
            let metadataFilters = {};
            _.each(grid.metadata.filters, metadata => metadataFilters[metadata.name] = metadata);

            _.each(this.filters, function(filter, name) {
                let metadata = metadataFilters[name];
                if (metadata) {
                    filter.visible = true;
                    filter.setRenderMode(this.renderMode);
                    filter.trigger('total-records-count-updated', this.collection.state.totalRecords);
                    filter.trigger('metadata-loaded', metadata);
                } else {
                    filter.visible = false;
                }

                delete metadataFilters[name];
            }, this);

            this.checkFiltersVisibility();
        },

        callWithCollection: function(callback) {
            callback(this.collection);
        }
    });

    return GallyFilterManager;
});
