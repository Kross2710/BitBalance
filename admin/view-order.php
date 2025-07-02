<!-- Modal Structure -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <button class="close-btn" id="closeViewModal" aria-label="Close modal">&times;</button>
        <h2 id="modal-title">Order #</h2>

        <table id="item-table" style="width:100%;border-collapse:collapse">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Line Total</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<style>
    .modal {
        display: none;
        position: fixed;
        /* Stay in place */
        z-index: 1000;
        /* Sit on top */
        left: 0;
        top: 0;
        width: 100%;
        /* Full width */
        height: 100%;
        /* Full height */
        overflow: auto;
        /* Enable scroll if needed */
        background-color: rgb(0, 0, 0, 0.3);
        /* Fallback color */
    }

    .modal-content {
        position: relative;
        background: white;
        margin: 80px auto;
        padding: 30px 24px;
        border-radius: 5px;
        max-width: 400px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.16);
        text-align: center;
    }

    .close-btn {
        position: absolute;
        top: 12px;
        right: 12px;
        width: 32px;
        height: 32px;
        border: none;
        color: #888;
        font-size: 24px;
        line-height: 1;
        border-radius: 50%;
        cursor: pointer;
        transition: background 0.2s, color 0.2s;
    }

    .close-btn:hover {
        color: #333;
    }

    .close {
        color: #aaa;
        position: absolute;
        top: 10px;
        right: 18px;
        font-size: 32px;
        font-weight: bold;
        cursor: pointer;
    }

    .close:hover {
        color: #000;
    }

    input {
        width: 100%;
        padding: 10px;
        margin: 10px 0;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    .login-btn {
        background-color: #4a7ee3;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    button:hover {
        background-color: #3a6bb3;
    }

    .modal p {
        margin-top: 10px;
    }
</style>