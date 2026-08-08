(() => {
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
