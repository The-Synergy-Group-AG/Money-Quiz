<div class="mq-container money-quiz-template-form">
    <?php require_once( MONEYQUIZ__PLUGIN_DIR . 'tabs.admin.php'); ?>
    <h3>reCAPTCHA Settings</h3>
    <?php echo $save_msg ;
    
    ?>
    <form method="post" action="" novalidate="novalidate" class="recaptcha-setting-option">
        
        <input name="action" value="recaptcha_setting" type="hidden">
        <?php wp_nonce_field(); ?>
        
        <table class="form-table mq-form-table">
            <tbody>
                <tr>
                    <th><label>Enable reCAPTCHA</label></th>
                    <td>
                        <input type="hidden" name="recaptcha_setting[1]" value="off">
                        <input type="checkbox" name="recaptcha_setting[1]" value="on" <?php echo isset($recaptcha_setting['1']) && $recaptcha_setting['1']=='on' ? 'checked' : ''; ?>>
                    </td>
                </tr>
                <tr>
                    <th><label>reCAPTCHA Version</label></th>
                    <td>
                        <select name="recaptcha_setting[2]">
                            <option value="v2" <?php echo isset($recaptcha_setting['2']) && $recaptcha_setting['2'] == 'v2' ? 'selected' : ''; ?>>v2</option>
                            <option value="v3" <?php echo isset($recaptcha_setting['2']) && $recaptcha_setting['2'] == 'v3' ? 'selected' : ''; ?>>v3</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label>Site Key</label></th>
                    <td><input type="text" name="recaptcha_setting[3]" value="<?php echo isset($recaptcha_setting['3']) ? $recaptcha_setting['3'] : ''; ?>" required></td>
                </tr>
                <tr>
                    <th><label>Secret Key</label></th>
                    <td><input type="text" name="recaptcha_setting[4]" value="<?php echo isset($recaptcha_setting['4']) ? $recaptcha_setting['4'] : ''; ?>" required></td>
                </tr>
                <tr>
                    <th scope="row">&nbsp;</th>
                    <td><p class="submit"><input name="submit" id="submit" class="button button-primary" value="Save Settings" type="submit"></p></td>
                </tr>
            </tbody>
        </table>
    </form>
</div>
