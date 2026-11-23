<div>
    <x-filament-panels::header :heading="$this->getHeading()" :actions="$this->getCachedHeaderActions()" />
    <div wire:poll.10s="checkForNewOrders"
         x-data="{
            ctx: null,
            destroy() { this.ctx?.close() },
            chime() {
                try {
                    // One audio context per page; browsers cap how many can be open.
                    this.ctx ??= new (window.AudioContext || window.webkitAudioContext)();
                    const ctx = this.ctx;
                    [880, 1175, 880].forEach((freq, i) => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.frequency.value = freq;
                        osc.connect(gain); gain.connect(ctx.destination);
                        gain.gain.setValueAtTime(0.2, ctx.currentTime + i * 0.25);
                        osc.start(ctx.currentTime + i * 0.25);
                        osc.stop(ctx.currentTime + i * 0.25 + 0.2);
                    });
                } catch (e) {}
            }
         }"
         x-on:new-app-order.window="chime(); new FilamentNotification().title(@js(__('New app order'))).warning().send()">
    </div>
</div>
