(() => {
    window.createRow = function createRow(data) {
        return `
            <div
                id="branch-${data.id}"
                class="branch-item item row relative group grid grid-cols-6 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'
                onclick="generateBranchModal(this)"
            >
                <span class="text-left pl-5 col-span-2">
                    <span class="font-medium">${data.name || "-"}</span>
                    ${(data.details?.["Main Branch"] || "No") === "Yes" ? '<span class="ml-2 text-xs text-[var(--border-success)]">Main</span>' : ""}
                </span>
                <span class="text-left pl-5">${data.code || "-"}</span>
                <span class="text-center font-mono text-xs">${data.details?.Prefix || "-"}</span>
                <span class="text-center text-[var(--secondary-text)]">${data.details?.Phone || "-"}</span>
                <span class="text-right pr-5 capitalize ${data.status === "active" ? "text-[var(--border-success)]" : "text-[var(--border-error)]"}">${data.status || "-"}</span>
            </div>
        `;
    };

    window.generateBranchModal = function generateBranchModal(item) {
        const data = JSON.parse(item.dataset.json || "{}");

        createModal({
            id: "branchDetailsModal",
            uId: data.id,
            status: data.status,
            image: data.image,
            name: data.name,
            details: data.details || {},
            profile: true,
            bottomActions: [
                {
                    id: "edit-branch",
                    text: "Edit",
                    link: data.edit_url,
                },
            ],
        });
    };
})();
