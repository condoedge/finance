function loadingCardPlaceholder() {
    const cardPlaceholder = document.createElement("div");
    cardPlaceholder.id = "card-element-placeholder";
    cardPlaceholder.textContent = 'Loading card element...';
    cardPlaceholder.style.position = 'absolute';
    cardPlaceholder.style.top = '-5px';
    cardPlaceholder.style.color = '#999';

    return cardPlaceholder;
}

function activateStripe() {
    const originalBtn = document.getElementById("pay-button");

    if (!originalBtn) {
        console.error("Pay button not found. Ensure it exists in the DOM.");
        return;
    }

    const duplicateBtn = originalBtn.cloneNode(true);
    duplicateBtn.id = "pay-button-stripe";
    originalBtn.parentNode.replaceChild(duplicateBtn, originalBtn);

    setTimeout(() => {
        const stripe = Stripe("{{STRIPE_PUBLIC_KEY}}");

        const elements = stripe.elements();
        const cardElement = elements.create("card");

        cardElement.mount("#card-element");

        const cardPlaceholder = loadingCardPlaceholder();

        cardElement._component.appendChild(
            cardPlaceholder
        );

        cardElement.on("ready", () => {
            cardPlaceholder.style.display = "none";
        });

        const cardButton = document.getElementById("pay-button-stripe");

        cardButton.addEventListener("click", async (e) => {
            $("#pay-button-stripe").prop("disabled", true);

            utils.setLoadingScreen();

            const { paymentMethod, error } = await stripe.createPaymentMethod({
                type: "card",
                card: cardElement,
                billing_details: {
                    name: $("[name=cardholder_name]").val(),
                    email: $("[name=cardholder_email]").val(),
                },
            });

            if (error) {
                $("#pay-button-stripe").prop("disabled", false);
                utils.removeLoadingScreen();
                alert(error.message);
            } else {
                $("#payment_method_id").val(paymentMethod.id);
                document
                    .querySelector("#payment_method_id")
                    .dispatchEvent(new Event("input")); //Super important!! This triggers Vue to re-read input values

                originalBtn.click(); //Trigger the original button click
            }
        });
    }, 200);
}
