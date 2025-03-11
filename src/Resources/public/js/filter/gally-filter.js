define(function(require) {
    'use strict';

    const $ = require('jquery');
    const _ = require('underscore');
    const routing = require('routing');
    const MultiSelectFilter = require('oro/filter/multiselect-filter');
    const LoadingMaskView = require('oroui/js/app/views/loading-mask-view');
    const __ = require('orotranslation/js/translator');
    const mediator = require('oroui/js/mediator');

    /**
     * Gally select filter: filter values as multiple select options and
     * add a view more button if all the options are not displayed
     *
     * @export  oro/filter/gally-filter
     * @class   oro.filter.GallyFilter
     * @extends oro.filter.MultiSelectFilter
     */
    const GallyFilter = MultiSelectFilter.extend({

        custom_data: {},

        /**
         * @inheritdoc
         */
        initialize: function (options) {
            GallyFilter.__super__.initialize.call(this, options);
        },

        /**
         * @inheritdoc
         */
        render: function() {
            GallyFilter.__super__.render.call(this);

            if (!this.showMoreLink) {
                const viewMoreLabel = __('gally.filter.showMore.label');
                this.showMoreLink = $('<a/>', {href: '#', html: viewMoreLabel, class: 'view-more', click: this.showMore.bind(this)});
                this.selectWidget.getWidget().append(this.showMoreLink);
                if (!this.subview('loading')) {
                    this.subview('loading', new LoadingMaskView({container: this.selectWidget.getWidget()}));
                }
                this.$el.on('input', function(event) {
                    if (event.target.value.length && this.custom_data.hasMore) {
                        this.showMore();
                    }
                }.bind(this));
            }

            if (this.custom_data.hasMore) {
                this.showMoreLink.show();
                if (this.$el.find('.datagrid-manager-search input').val()) {
                    this.showMore();
                }
            } else {
                this.showMoreLink.hide();
            }
        },

        onMetadataLoaded: function(metadata) {
            this.custom_data = metadata.custom_data;
            this.choices = metadata.choices;
            GallyFilter.__super__.onMetadataLoaded.call(this, metadata);
        },

        filterTemplateData: function(data) {
            if (this.counts === null) {
                return data;
            } else if (_.isEmpty(this.counts)) {
                this.counts = Object.create(null);
            }

            let options = $.extend(true, {}, this.choices || {});
            const filterOptions = option => {
                if (this.isDisableFiltersEnabled && _.has(this.countsWithoutFilters, option.value)) {
                    option.disabled = true;
                } else {
                    options = _.without(options, option);
                }
            };

            _.each(options, option => {
                this.counts[option.value] = option.count;
                // option.count = this.counts[option.value] || 0;
                option.disabled = false;
                if (option.count === 0 &&
                    !_.contains(data.selected.value, option.value)
                ) {
                    filterOptions(option);
                }
            });

            const nonZeroOptions = _.filter(options, option => {
                return option.count > 0;
            });
            if (nonZeroOptions.length === 1) {
                _.each(options, option => {
                    if (option.count === this.totalRecordsCount &&
                        !_.contains(data.selected.value, option.value)
                    ) {
                        filterOptions(option);
                    }
                });
            }

            this.visible = !_.isEmpty(options);
            data.options = options;

            return data;
        },

        showMore: function() {
            this.custom_data.hasMore = false;

            mediator.trigger(
                'datagrid:call_with_collection',
                function (collection) {
                    this.subview('loading').show();
                    let params = collection.getFetchData();
                    params['gridName'] = collection.inputName;
                    params['field'] = this.name;

                    $.ajax({
                        url: routing.generate('gally_filter_view_more', params),
                        method: 'GET',
                        success: function (response) {
                            this.showMoreLink.hide();
                            this._setChoices(response);
                            this.render();
                            this.subview('loading').hide();
                        }.bind(this),
                        error: function (xhr) {
                            this.subview('loading').hide();
                        }.bind(this),
                    });
                }.bind(this)
            );
        }
    });

    return GallyFilter;
});
