<?php

/**
 * Upload container class
 *
 * @property string $filePath File path for the upload relative to the user's data dir
 * @access private
 */
class CKEditorUpload extends ElggObject {
	protected function initializeAttributes() {
		parent::initializeAttributes();
		$this->attributes['subtype'] = "ckeditor_upload";
		$this->attributes['access_id'] = ACCESS_PRIVATE;
	}

	public function getURL() {
		$user_guid = $this->getOwnerGUID();
		$basename = pathinfo($this->filePath, PATHINFO_BASENAME);
		$url = "uploads/images/$user_guid/$basename";
		return elgg_normalize_url($url);
	}
}
