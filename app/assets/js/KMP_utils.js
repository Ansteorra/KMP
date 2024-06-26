import Autocomplete from './autocomplete.js';
export default {
    populateList(url, filterId1, filterid2, valKey, dispKey, selectElementId) {
        //if url ends with a / remove it
        if (url.endsWith('/')) {
            url = url.slice(0, -1);
        }
        url = url + '/' + filterId1;
        if (filterid2) {
            url = url + '/' + filterid2;
        }
        $.get(url, function (data) {
            //remove all options
            $('#' + selectElementId).find('option').remove();
            //add new options
            $('#' + selectElementId).append('<option value="0"></option>');
            $.each(data, function (key, value) {
                $('#' + selectElementId).append('<option value="' + value[valKey] + '">' + value[dispKey] + '</option>');
            });
        });
    },

    configureAutoComplete(ac, searchUrl, inputFieldId, valKey, dispKey, resultElementId, findMatchedCallback, findFailCallback) {
        ac = new Autocomplete($('#' + inputFieldId)[0], {
            data: [],
            treshold: 3,
            maximumItems: 8,
            onInput: () => {
                $('#' + resultElementId).val(0).trigger('change');
                var input = $('#' + inputFieldId).val();
                var me = this;
                //AJAX call to get data
                $.ajax({
                    url: searchUrl,
                    dataType: 'json',
                    type: 'GET',
                    data: { q: input },
                    success: function (data) {
                        var sendData = [];
                        for (var i = 0; i < data.length; i++) {
                            sendData.push({ label: data[i][dispKey], value: data[i][valKey] });
                        }
                        if (data.length == 0 && findFailCallback) {
                            findFailCallback(input);
                        }
                        else {
                            if (findMatchedCallback)
                                findMatchedCallback(sendData);
                            ac.setData(sendData);
                        }
                    }
                });
            },
            onSelectItem: ({ label, value }) => {
                $('#' + resultElementId).val(value).trigger('change');
            }
        });
    },

    urlParam(name) {
        var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
        var result = null;
        if (results) {
            result = decodeURIComponent(results[1]);
        }
        return result;
    }
};