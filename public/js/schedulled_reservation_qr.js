document.addEventListener("DOMContentLoaded", () => {
    const btn = document.getElementById("btn-cancel");
    if (!btn) return;

    btn.addEventListener("click", async () => {
        const ok = window.confirm(
            "Yakin ingin membatalkan reservasi ini? Tindakan ini tidak dapat dibatalkan."
        );
        if (!ok) return;

        const url = btn.dataset.cancelUrl;
        const token = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content");

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = "Membatalkan...";

        try {
            const res = await fetch(url, {
                method: "DELETE",
                headers: {
                    "X-CSRF-TOKEN": token,
                    Accept: "application/json",
                },
            });

            if (!res.ok) {
                const text = await res.text();
                throw new Error(text || `HTTP ${res.status}`);
            }

            const data = await res.json().catch(() => ({}));
            alert(data.message || "Reservasi dibatalkan.");
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                window.location.reload();
            }
        } catch (err) {
            console.error(err);
            alert("Gagal membatalkan reservasi. Silakan coba lagi.");
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });
});
