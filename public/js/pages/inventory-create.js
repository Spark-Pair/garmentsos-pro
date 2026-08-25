(() => {
    document.addEventListener('wizard:step-changed', event => {
        document.getElementById('lastRecordStep1')?.classList.toggle('hidden', event.detail.step !== 1);
        document.getElementById('lastRecordStep2')?.classList.toggle('hidden', event.detail.step !== 2);
    });
    window.calculateInventoryAmount = function calculateInventoryAmount() {
        const quantity = parseFloat(document.getElementById("quantity")?.value || "0");
        const unitPrice = parseFloat(document.getElementById("unit_price")?.value || "0");
        const amount = document.getElementById("amount");
        if (!amount) return;
        amount.value = quantity > 0 && unitPrice >= 0 ? (quantity * unitPrice).toFixed(2) : "";
    };

    window.trackInventoryType = function trackInventoryType(elem) {
        const fabricInput = document.getElementById("fabric_id");
        if (!fabricInput) return;
        fabricInput.value = "-- Select Fabric --";
        fabricInput.disabled = elem.value !== "fabric";
        
    };

    window.validateForNextStep = function() {
        let isValid = true;

        return isValid;
    }
})();
