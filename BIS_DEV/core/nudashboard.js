/**
 * Low-Code Customizable Dashboard & Lazy Loading Widget Engine for nuBuilder
 */

window.nuDashboard = {
    layout: null,
    isCustomizing: false,
    observer: null,

    init: function() {
        if ($('#nu-dashboard-view').length === 0) {
            this.injectContainers();
        }
        this.setupObserver();
        this.loadLayout();
    },

    injectContainers: function() {
        var html = [
            '<style>',
            '#nu-dashboard-view { padding: 20px; font-family: sans-serif; background: #f8fafc; min-height: 80vh; display: none; }',
            '.nu-dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; }',
            '.nu-dash-title { font-size: 22px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 10px; }',
            '.nu-dash-actions { display: flex; gap: 10px; }',
            '.nu-dash-btn { background: #3b82f6; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; transition: all 0.2s; }',
            '.nu-dash-btn:hover { background: #2563eb; }',
            '.nu-dash-btn-secondary { background: #64748b; }',
            '.nu-dash-btn-secondary:hover { background: #475569; }',
            '.nu-dash-btn-success { background: #10b981; }',
            '.nu-dash-btn-success:hover { background: #059669; }',
            '.nu-dash-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 16px; }',
            '.nu-widget-card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 16px; min-height: 120px; position: relative; transition: transform 0.2s, box-shadow 0.2s; }',
            '.nu-widget-card:hover { box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }',
            '.nu-widget-card.editing { border: 2px dashed #3b82f6; background: #eff6ff; }',
            '.col-3 { grid-column: span 3; }',
            '.col-4 { grid-column: span 4; }',
            '.col-6 { grid-column: span 6; }',
            '.col-8 { grid-column: span 8; }',
            '.col-12 { grid-column: span 12; }',
            '@media (max-width: 900px) { .col-3, .col-4, .col-6, .col-8 { grid-column: span 12; } }',
            '.nu-widget-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }',
            '.nu-widget-title { font-size: 14px; font-weight: 600; color: #475569; margin: 0; text-transform: uppercase; letter-spacing: 0.5px; }',
            '.nu-widget-remove { color: #ef4444; cursor: pointer; font-size: 16px; display: none; }',
            '.editing .nu-widget-remove { display: inline-block; }',
            '.nu-widget-body { position: relative; min-height: 60px; }',
            '.nu-kpi-val { font-size: 28px; font-weight: 800; color: #0f172a; line-height: 1.1; margin-top: 4px; }',
            '.nu-kpi-sub { font-size: 12px; color: #64748b; margin-top: 4px; }',
            '.nu-kpi-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; float: right; background: #dcfce7; color: #15803d; }',
            '.nu-loading-spinner { display: flex; align-items: center; justify-content: center; height: 80px; color: #94a3b8; font-size: 13px; gap: 8px; }',
            '.nu-chart-bar-container { display: flex; align-items: flex-end; gap: 12px; height: 140px; padding-top: 10px; border-bottom: 1px solid #e2e8f0; }',
            '.nu-chart-bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; }',
            '.nu-chart-bar { width: 100%; max-width: 30px; background: #3b82f6; border-radius: 4px 4px 0 0; transition: height 0.5s ease; min-height: 4px; }',
            '.nu-chart-label { font-size: 11px; color: #64748b; margin-top: 6px; }',
            '.nu-table-widget { width: 100%; border-collapse: collapse; font-size: 13px; }',
            '.nu-table-widget th { text-align: left; padding: 8px; background: #f8fafc; color: #475569; font-weight: 600; border-bottom: 1px solid #e2e8f0; }',
            '.nu-table-widget td { padding: 8px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }',
            '.nu-shortcut-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }',
            '.nu-shortcut-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; text-align: center; cursor: pointer; transition: all 0.2s; text-decoration: none; color: #334155; font-weight: 600; font-size: 12px; }',
            '.nu-shortcut-item:hover { background: #eff6ff; border-color: #93c5fd; color: #1d4ed8; }',
            '.nu-modal-backdrop { position: fixed; top: 0; left: 0; right:0; bottom:0; background: rgba(0,0,0,0.5); z-index: 9999; display: none; align-items: center; justify-content: center; }',
            '.nu-modal-box { background: white; padding: 24px; border-radius: 8px; width: 450px; max-width: 90%; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }',
            '.nu-modal-box h3 { margin-top: 0; margin-bottom: 16px; color: #0f172a; }',
            '.nu-form-group { margin-bottom: 14px; }',
            '.nu-form-group label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; }',
            '.nu-form-group input, .nu-form-group select { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; box-sizing: border-box; }',
            '</style>',
            '<div id="nu-dashboard-view">',
            '  <div class="nu-dash-header">',
            '    <h1 class="nu-dash-title"><i class="fa fa-dashboard" style="color:#3b82f6;"></i> Low-Code Dynamic Dashboard</h1>',
            '    <div class="nu-dash-actions">',
            '      <button class="nu-dash-btn nu-dash-btn-secondary" id="btn-toggle-customize" onclick="nuDashboard.toggleCustomize()"><i class="fa fa-pencil"></i> Customize</button>',
            '      <button class="nu-dash-btn nu-dash-btn-success" id="btn-add-widget" style="display:none;" onclick="nuDashboard.showAddModal()"><i class="fa fa-plus"></i> Add Widget</button>',
            '      <button class="nu-dash-btn" id="btn-save-layout" style="display:none;" onclick="nuDashboard.saveLayout()"><i class="fa fa-save"></i> Save Layout</button>',
            '    </div>',
            '  </div>',
            '  <div class="nu-dash-grid" id="nu-dash-grid-container"></div>',
            '</div>',
            '<div class="nu-modal-backdrop" id="nu-widget-modal">',
            '  <div class="nu-modal-box">',
            '    <h3>Add Custom Widget</h3>',
            '    <div class="nu-form-group">',
            '      <label>Widget Title</label>',
            '      <input type="text" id="nu-new-widget-title" placeholder="e.g. Total Revenue">',
            '    </div>',
            '    <div class="nu-form-group">',
            '      <label>Widget Type</label>',
            '      <select id="nu-new-widget-type" onchange="nuDashboard.onWidgetTypeChange()">',
            '        <option value="kpi">KPI Stat Card</option>',
            '        <option value="chart">Activity Chart</option>',
            '        <option value="table">Recent Data Table</option>',
            '        <option value="shortcuts">Quick Action Shortcuts</option>',
            '      </select>',
            '    </div>',
            '    <div class="nu-form-group" id="group-kpi-metric">',
            '      <label>KPI Metric Source</label>',
            '      <select id="nu-new-widget-metric">',
            '        <option value="users">Active System Users</option>',
            '        <option value="forms">Configured System Forms</option>',
            '        <option value="objects">Total UI Objects</option>',
            '        <option value="redis">Redis Memory Usage</option>',
            '      </select>',
            '    </div>',
            '    <div class="nu-form-group">',
            '      <label>Card Width</label>',
            '      <select id="nu-new-widget-width">',
            '        <option value="col-3">1/4 Width (3 Columns)</option>',
            '        <option value="col-4">1/3 Width (4 Columns)</option>',
            '        <option value="col-6">Half Width (6 Columns)</option>',
            '        <option value="col-8">2/3 Width (8 Columns)</option>',
            '        <option value="col-12">Full Width (12 Columns)</option>',
            '      </select>',
            '    </div>',
            '    <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">',
            '      <button class="nu-dash-btn nu-dash-btn-secondary" onclick="nuDashboard.hideAddModal()">Cancel</button>',
            '      <button class="nu-dash-btn" onclick="nuDashboard.addCustomWidget()">Add Widget</button>',
            '    </div>',
            '  </div>',
            '</div>'
        ].join('\n');

        if ($('#nuRECORD').length > 0) {
            $('#nuRECORD').append(html);
        } else {
            $('body').append(html);
        }
    },

    setupObserver: function() {
        var self = this;
        if ('IntersectionObserver' in window) {
            this.observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var card = $(entry.target);
                        if (!card.data('loaded')) {
                            card.data('loaded', true);
                            self.loadWidgetData(card);
                        }
                    }
                });
            }, { threshold: 0.1 });
        }
    },

    loadLayout: function() {
        var self = this;
        $.ajax({
            url: 'actions/dashboard_actions.php?action=get_layout',
            method: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success' && res.layout) {
                    self.layout = res.layout;
                    self.renderGrid();
                }
            }
        });
    },

    renderGrid: function() {
        var container = $('#nu-dash-grid-container');
        container.empty();

        if (!this.layout || !this.layout.widgets) return;

        var self = this;
        this.layout.widgets.forEach(function(w) {
            var cardHtml = [
                '<div class="nu-widget-card ' + w.width + '" id="' + w.id + '" data-type="' + w.type + '" data-metric="' + (w.config.metric || '') + '">',
                '  <div class="nu-widget-header">',
                '    <h3 class="nu-widget-title">' + w.title + '</h3>',
                '    <i class="fa fa-trash nu-widget-remove" title="Remove Widget" onclick="nuDashboard.removeWidget(\'' + w.id + '\')"></i>',
                '  </div>',
                '  <div class="nu-widget-body">',
                '    <div class="nu-loading-spinner"><i class="fa fa-circle-o-notch fa-spin"></i> Lazy loading...</div>',
                '  </div>',
                '</div>'
            ].join('\n');

            var $card = $(cardHtml);
            container.append($card);

            if (self.observer) {
                self.observer.observe($card[0]);
            } else {
                self.loadWidgetData($card);
            }
        });

        if (this.isCustomizing) {
            $('.nu-widget-card').addClass('editing');
        }
    },

    loadWidgetData: function($card) {
        var widgetId = $card.attr('id');
        var widgetType = $card.data('type');
        var metric = $card.data('metric');
        var $body = $card.find('.nu-widget-body');

        $.ajax({
            url: 'actions/dashboard_actions.php?action=get_widget_data',
            method: 'GET',
            data: { widget_id: widgetId, widget_type: widgetType, metric: metric },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success' && res.data) {
                    window.nuDashboard.renderWidgetContent($body, widgetType, res.data);
                } else {
                    $body.html('<p style="color:#ef4444; font-size:12px;">Failed to load data</p>');
                }
            },
            error: function() {
                $body.html('<p style="color:#ef4444; font-size:12px;">Error connecting to dashboard server</p>');
            }
        });
    },

    renderWidgetContent: function($body, type, data) {
        if (type === 'kpi') {
            var html = [
                '<span class="nu-kpi-badge">' + (data.change || '+0%') + '</span>',
                '<div class="nu-kpi-val">' + data.value + '</div>',
                '<div class="nu-kpi-sub">' + (data.subtext || '') + '</div>'
            ].join('\n');
            $body.html(html);
        } else if (type === 'chart') {
            var labels = data.labels || [];
            var series = data.series && data.series[0] ? data.series[0].data : [];
            var maxVal = Math.max.apply(null, series.concat([1]));

            var barHtml = '<div class="nu-chart-bar-container">';
            series.forEach(function(val, idx) {
                var pct = Math.round((val / maxVal) * 100);
                barHtml += '<div class="nu-chart-bar-col">';
                barHtml += '  <div class="nu-chart-bar" style="height:' + pct + '%;" title="' + val + '"></div>';
                barHtml += '  <span class="nu-chart-label">' + (labels[idx] || '') + '</span>';
                barHtml += '</div>';
            });
            barHtml += '</div>';
            $body.html(barHtml);
        } else if (type === 'table') {
            var rows = data.rows || [];
            var tableHtml = '<table class="nu-table-widget"><thead><tr><th>ID</th><th>Code</th><th>Description</th></tr></thead><tbody>';
            rows.forEach(function(r) {
                tableHtml += '<tr><td>' + r.id + '</td><td><b>' + r.code + '</b></td><td>' + r.description + '</td></tr>';
            });
            tableHtml += '</tbody></table>';
            $body.html(tableHtml);
        } else if (type === 'shortcuts') {
            var items = data.items || [];
            var gridHtml = '<div class="nu-shortcut-grid">';
            items.forEach(function(it) {
                gridHtml += '<div class="nu-shortcut-item" onclick="' + (it.action || '') + '">';
                gridHtml += '  <i class="fa ' + (it.icon || 'fa-star') + '" style="font-size:18px; margin-bottom:4px; display:block; color:#3b82f6;"></i>';
                gridHtml +=    it.title;
                gridHtml += '</div>';
            });
            gridHtml += '</div>';
            $body.html(gridHtml);
        }
    },

    toggleCustomize: function() {
        this.isCustomizing = !this.isCustomizing;
        if (this.isCustomizing) {
            $('#btn-toggle-customize').addClass('nu-dash-btn-secondary').html('<i class="fa fa-check"></i> Done Editing');
            $('#btn-add-widget, #btn-save-layout').show();
            $('.nu-widget-card').addClass('editing');
        } else {
            $('#btn-toggle-customize').removeClass('nu-dash-btn-secondary').html('<i class="fa fa-pencil"></i> Customize');
            $('#btn-add-widget, #btn-save-layout').hide();
            $('.nu-widget-card').removeClass('editing');
        }
    },

    showAddModal: function() {
        $('#nu-widget-modal').css('display', 'flex');
    },

    hideAddModal: function() {
        $('#nu-widget-modal').hide();
    },

    onWidgetTypeChange: function() {
        var type = $('#nu-new-widget-type').val();
        if (type === 'kpi') {
            $('#group-kpi-metric').show();
        } else {
            $('#group-kpi-metric').hide();
        }
    },

    addCustomWidget: function() {
        var title = $('#nu-new-widget-title').val() || 'Custom Widget';
        var type = $('#nu-new-widget-type').val();
        var metric = $('#nu-new-widget-metric').val();
        var width = $('#nu-new-widget-width').val();

        var newId = 'w_custom_' + Date.now();
        var newWidget = {
            id: newId,
            type: type,
            title: title,
            width: width,
            config: { metric: metric }
        };

        if (!this.layout) { this.layout = { widgets: [] }; }
        this.layout.widgets.push(newWidget);

        this.hideAddModal();
        this.renderGrid();
    },

    removeWidget: function(id) {
        if (!this.layout || !this.layout.widgets) return;
        this.layout.widgets = this.layout.widgets.filter(function(w) { return w.id !== id; });
        $('#' + id).remove();
    },

    saveLayout: function() {
        var self = this;
        $.ajax({
            url: 'actions/dashboard_actions.php?action=save_layout',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ layout: self.layout }),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    alert('Dashboard configuration saved successfully!');
                    self.toggleCustomize();
                } else {
                    alert('Failed to save dashboard: ' + (res.message || 'Unknown error'));
                }
            }
        });
    }
};

window.nuOpenDashboard = function() {
    $('[data-nu-tab], [data-nu-form]').hide();
    $('#nu-dashboard-view').show();
    window.nuDashboard.init();
};

$(document).ready(function() {
    if ($('#nu-dashboard-btn').length === 0 && $('#nuTabHolder').length > 0) {
        var btn = '<button id="nu-dashboard-btn" class="nuTab nuDragNoSelect" onclick="nuOpenDashboard()" style="margin-left:8px; background:#3b82f6; color:white;"><i class="fa fa-dashboard"></i> Dashboard</button>';
        $('#nuTabHolder').append(btn);
    }
});
