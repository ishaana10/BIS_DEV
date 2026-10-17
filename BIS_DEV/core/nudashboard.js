/**
 * Industrial Aesthetic Low-Code Dynamic Dashboard & Lazy-Loading Widget Engine for nuBuilder
 * Data loading is fully procedure-driven asynchronously so page rendering is never blocked.
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
            '#nu-dashboard-view { padding: 24px; font-family: "Segoe UI", system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; min-height: 85vh; display: none; }',
            '.nu-dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 2px solid #334155; padding-bottom: 16px; }',
            '.nu-dash-title { font-size: 24px; font-weight: 800; color: #f8fafc; margin: 0; display: flex; align-items: center; gap: 12px; letter-spacing: -0.5px; }',
            '.nu-dash-title i { color: #38bdf8; text-shadow: 0 0 10px rgba(56, 189, 248, 0.4); }',
            '.nu-dash-subtitle { font-size: 12px; color: #94a3b8; font-weight: 400; margin-top: 4px; font-family: monospace; }',
            '.nu-dash-actions { display: flex; gap: 12px; }',
            '.nu-dash-btn { background: #0284c7; color: white; border: 1px solid #38bdf8; padding: 9px 18px; border-radius: 6px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.3); }',
            '.nu-dash-btn:hover { background: #0369a1; transform: translateY(-1px); }',
            '.nu-dash-btn-secondary { background: #334155; border-color: #64748b; color: #e2e8f0; }',
            '.nu-dash-btn-secondary:hover { background: #475569; }',
            '.nu-dash-btn-success { background: #059669; border-color: #34d399; }',
            '.nu-dash-btn-success:hover { background: #047857; }',
            '.nu-dash-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 20px; }',
            '.nu-widget-card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.25); padding: 20px; min-height: 130px; position: relative; transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s; }',
            '.nu-widget-card:hover { border-color: #38bdf8; box-shadow: 0 8px 20px rgba(0,0,0,0.4); }',
            '.nu-widget-card.editing { border: 2px dashed #38bdf8; background: #0f172a; }',
            '.col-3 { grid-column: span 3; }',
            '.col-4 { grid-column: span 4; }',
            '.col-6 { grid-column: span 6; }',
            '.col-8 { grid-column: span 8; }',
            '.col-12 { grid-column: span 12; }',
            '@media (max-width: 900px) { .col-3, .col-4, .col-6, .col-8 { grid-column: span 12; } }',
            '.nu-widget-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid #334155; padding-bottom: 8px; }',
            '.nu-widget-title { font-size: 12px; font-weight: 700; color: #94a3b8; margin: 0; text-transform: uppercase; letter-spacing: 1px; font-family: monospace; }',
            '.nu-widget-remove { color: #f87171; cursor: pointer; font-size: 16px; display: none; transition: color 0.2s; }',
            '.nu-widget-remove:hover { color: #ef4444; }',
            '.editing .nu-widget-remove { display: inline-block; }',
            '.nu-widget-body { position: relative; min-height: 70px; }',
            '.nu-kpi-val { font-size: 32px; font-weight: 900; color: #f8fafc; line-height: 1; margin-top: 6px; font-family: "Courier New", monospace; letter-spacing: -1px; }',
            '.nu-kpi-sub { font-size: 12px; color: #64748b; margin-top: 6px; font-weight: 500; }',
            '.nu-kpi-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; float: right; background: #064e3b; color: #34d399; border: 1px solid #059669; font-family: monospace; }',
            '.nu-loading-spinner { display: flex; align-items: center; justify-content: center; height: 90px; color: #64748b; font-size: 13px; gap: 10px; font-family: monospace; }',
            '.nu-chart-bar-container { display: flex; align-items: flex-end; gap: 16px; height: 150px; padding-top: 16px; border-bottom: 1px solid #334155; }',
            '.nu-chart-bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; }',
            '.nu-chart-bar { width: 100%; max-width: 36px; background: linear-gradient(180deg, #38bdf8 0%, #0284c7 100%); border-radius: 4px 4px 0 0; transition: height 0.6s cubic-bezier(0.4, 0, 0.2, 1); min-height: 4px; box-shadow: 0 0 8px rgba(56, 189, 248, 0.3); }',
            '.nu-chart-label { font-size: 11px; color: #94a3b8; font-family: monospace; }',
            '.nu-table-widget { width: 100%; border-collapse: collapse; font-size: 13px; font-family: monospace; }',
            '.nu-table-widget th { text-align: left; padding: 10px; background: #0f172a; color: #38bdf8; font-weight: 700; border-bottom: 2px solid #334155; }',
            '.nu-table-widget td { padding: 10px; border-bottom: 1px solid #334155; color: #cbd5e1; }',
            '.nu-shortcut-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }',
            '.nu-shortcut-item { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 14px; text-align: center; cursor: pointer; transition: all 0.2s; text-decoration: none; color: #f8fafc; font-weight: 700; font-size: 12px; font-family: monospace; }',
            '.nu-shortcut-item:hover { background: #1e293b; border-color: #38bdf8; color: #38bdf8; transform: translateY(-2px); }',
            '.nu-modal-backdrop { position: fixed; top: 0; left: 0; right:0; bottom:0; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(4px); z-index: 9999; display: none; align-items: center; justify-content: center; }',
            '.nu-modal-box { background: #1e293b; border: 1px solid #334155; padding: 28px; border-radius: 12px; width: 480px; max-width: 90%; box-shadow: 0 20px 40px rgba(0,0,0,0.6); color: #f8fafc; }',
            '.nu-modal-box h3 { margin-top: 0; margin-bottom: 20px; color: #f8fafc; font-size: 18px; font-weight: 800; border-bottom: 1px solid #334155; padding-bottom: 10px; }',
            '.nu-form-group { margin-bottom: 16px; }',
            '.nu-form-group label { display: block; font-size: 12px; font-weight: 700; color: #94a3b8; margin-bottom: 6px; font-family: monospace; }',
            '.nu-form-group input, .nu-form-group select { width: 100%; padding: 10px; background: #0f172a; border: 1px solid #334155; border-radius: 6px; font-size: 13px; color: #f8fafc; box-sizing: border-box; font-family: inherit; }',
            '.nu-form-group input:focus, .nu-form-group select:focus { outline: none; border-color: #38bdf8; }',
            '</style>',
            '<div id="nu-dashboard-view">',
            '  <div class="nu-dash-header">',
            '    <div>',
            '      <h1 class="nu-dash-title"><i class="fa fa-tachometer"></i> Industrial Operations Center</h1>',
            '      <div class="nu-dash-subtitle">PROCEDURAL ASYNCHRONOUS DASHBOARD • ZERO BLOCKING RENDER</div>',
            '    </div>',
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
            '    <h3><i class="fa fa-sliders" style="color:#38bdf8;"></i> Add Industrial Widget</h3>',
            '    <div class="nu-form-group">',
            '      <label>Widget Title</label>',
            '      <input type="text" id="nu-new-widget-title" placeholder="e.g. Total Revenue / Operational Throughput">',
            '    </div>',
            '    <div class="nu-form-group">',
            '      <label>Widget Type</label>',
            '      <select id="nu-new-widget-type" onchange="nuDashboard.onWidgetTypeChange()">',
            '        <option value="kpi">KPI Metric Counter</option>',
            '        <option value="chart">System Activity Histogram</option>',
            '        <option value="table">Recent Operations Table</option>',
            '        <option value="shortcuts">Quick Command Grid</option>',
            '      </select>',
            '    </div>',
            '    <div class="nu-form-group" id="group-kpi-metric">',
            '      <label>KPI Metric Procedure Source</label>',
            '      <select id="nu-new-widget-metric">',
            '        <option value="users">Active System Users</option>',
            '        <option value="forms">Configured System Forms</option>',
            '        <option value="objects">Total UI Objects</option>',
            '        <option value="redis">Redis Memory Footprint</option>',
            '      </select>',
            '    </div>',
            '    <div class="nu-form-group">',
            '      <label>Grid Column Span</label>',
            '      <select id="nu-new-widget-width">',
            '        <option value="col-3">Quarter Width (3 Columns)</option>',
            '        <option value="col-4">Third Width (4 Columns)</option>',
            '        <option value="col-6">Half Width (6 Columns)</option>',
            '        <option value="col-8">Two-Thirds Width (8 Columns)</option>',
            '        <option value="col-12">Full Grid Span (12 Columns)</option>',
            '      </select>',
            '    </div>',
            '    <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px;">',
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
                '    <div class="nu-loading-spinner"><i class="fa fa-cog fa-spin"></i> FETCHING PROCEDURE DATA...</div>',
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
                    $body.html('<p style="color:#f87171; font-size:12px; font-family:monospace;">PROCEDURE EXECUTION FAILED</p>');
                }
            },
            error: function() {
                $body.html('<p style="color:#f87171; font-size:12px; font-family:monospace;">PROCEDURE TIMEOUT / OFFLINE</p>');
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
                gridHtml += '  <i class="fa ' + (it.icon || 'fa-bolt') + '" style="font-size:20px; margin-bottom:6px; display:block; color:#38bdf8;"></i>';
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
        var title = $('#nu-new-widget-title').val() || 'Industrial Widget';
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
                    alert('Industrial Dashboard configuration saved successfully!');
                    self.toggleCustomize();
                } else {
                    alert('Failed to save layout: ' + (res.message || 'Unknown error'));
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
        var btn = '<button id="nu-dashboard-btn" class="nuTab nuDragNoSelect" onclick="nuOpenDashboard()" style="margin-left:8px; background:#0284c7; color:white; border:1px solid #38bdf8;"><i class="fa fa-dashboard"></i> Dashboard</button>';
        $('#nuTabHolder').append(btn);
    }
});
