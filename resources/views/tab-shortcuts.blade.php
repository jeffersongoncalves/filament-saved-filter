{{-- Alt+1..9 → Nth view ("All" first), Alt+] / Alt+[ → next / previous. Physical keys (event.code), so layouts and macOS Option don't matter. --}}
<div
    x-data="{
        handle(event) {
            if (! event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return
            if (event.target.closest('input, textarea, select, [contenteditable]:not([contenteditable=false])')) return

            const digit = event.code.match(/^Digit([1-9])$/)

            if (digit) {
                event.preventDefault()
                $wire.selectSavedFilter(Number(digit[1]) - 1)
            } else if (event.code === 'BracketRight' || event.code === 'BracketLeft') {
                event.preventDefault()
                $wire.cycleSavedFilter(event.code === 'BracketRight' ? 1 : -1)
            }
        },
    }"
    x-on:keydown.window="handle($event)"
    data-saved-filter-shortcuts
    hidden
></div>
