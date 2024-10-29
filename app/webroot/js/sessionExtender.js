function extendSesh(url) {
    setTimeout(function () {
        alert('Session Expiring! Click ok to extend session.');
        fetch(url)
            .then(res => {
                return res.json()
            })
            .then(data => {
                console.log(data.response)
                extendSesh(url)
            })
        //minutes * 60000 miliseconds per minute
    }, 25 * 60000)

}
