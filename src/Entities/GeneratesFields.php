<?php
namespace EMedia\Formation\Entities;

trait GeneratesFields
{

	public function getEditableFields()
	{
		return $this->editable;
	}

}