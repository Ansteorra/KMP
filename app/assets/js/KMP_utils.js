
export default {
    urlParam(name) {
        var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
        var result = null;
        if (results) {
            result = decodeURIComponent(results[1]);
        }
        return result;
    },

    sanitizeString(str) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#x27;',
            "/": '&#x2F;',
        };
        const reg = /[&<>"'/]/ig;
        return str.replace(reg, (match) => (map[match]));
    }
};