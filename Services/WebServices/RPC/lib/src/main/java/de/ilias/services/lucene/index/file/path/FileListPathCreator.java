/*
        +-----------------------------------------------------------------------------+
        | ILIAS open source                                                           |
        +-----------------------------------------------------------------------------+
        | Copyright (c) 1998-2001 ILIAS open source, University of Cologne            |
        |                                                                             |
        | This program is free software; you can redistribute it and/or               |
        | modify it under the terms of the GNU General Public License                 |
        | as published by the Free Software Foundation; either version 2              |
        | of the License, or (at your option) any later version.                      |
        |                                                                             |
        | This program is distributed in the hope that it will be useful,             |
        | but WITHOUT ANY WARRANTY; without even the implied warranty of              |
        | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
        | GNU General Public License for more details.                                |
        |                                                                             |
        | You should have received a copy of the GNU General Public License           |
        | along with this program; if not, write to the Free Software                 |
        | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
        +-----------------------------------------------------------------------------+
*/

package de.ilias.services.lucene.index.file.path;

import de.ilias.services.db.DBFactory;
import de.ilias.services.lucene.index.CommandQueueElement;
import de.ilias.services.settings.ClientSettings;
import de.ilias.services.settings.ConfigurationException;
import de.ilias.services.settings.LocalSettings;
import org.apache.logging.log4j.LogManager;
import org.apache.logging.log4j.Logger;

import java.io.File;
import java.sql.ResultSet;
import java.sql.SQLException;

/**
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 */
public class FileListPathCreator implements PathCreator {

	protected static Logger logger = LogManager.getLogger(FileListPathCreator.class);

	protected String basePath = "ilFiles";
	
	
	/**
	 * Default constructor
	 */
	public FileListPathCreator() {

	}
	
	/**
	 * Set bas path
	 * @param bp
	 * @return 
	 */
	public void setBasePath(String bp) {
		
		this.basePath = bp;
	}

	/**
	 * Get base path of file directory
	 * ILIAS version <= 4.0 (ilFiles)
	 * ILIAS version >= 4.1 (ilFile) 
	 * @return
	 */
	public String getBasePath() {
		
		return this.basePath;
	}
	
	
	public File buildFile(CommandQueueElement el, ResultSet res) throws PathCreatorException
	{
		StringBuilder fullPath = new StringBuilder();
		StringBuilder versionPath = new StringBuilder();
		
		File file;
		
		try {
			
			int objId = Integer.parseInt(DBFactory.getInt(res,"file_id"));

			fullPath.append(ClientSettings.getInstance(LocalSettings.getClientKey()).getDataDirectory().getAbsolutePath());
			fullPath.append(System.getProperty("file.separator"));
			fullPath.append(ClientSettings.getInstance(LocalSettings.getClientKey()).getClient());
			fullPath.append(System.getProperty("file.separator"));
			fullPath.append(getBasePath());
			
	
			fullPath.append(System.getProperty("file.separator"));
			fullPath.append(PathUtils.buildSplittedPathFromId(objId,"file"));
			
			versionPath.append(fullPath);
			versionPath.append(PathUtils.buildVersionDirectory(res.getInt("version")));
			versionPath.append(System.getProperty("file.separator"));
			versionPath.append(DBFactory.getString(res,"file_name"));

			file = new File(versionPath.toString());
			if(file.exists() && file.canRead()) {
				return file;
			}

			// Older versions do not store the files in version directories
			fullPath.append(DBFactory.getString(res, "file_name"));
			file = new File(fullPath.toString());
			if(file.exists() && file.canRead()) {
				return file;
			}
			if(!file.exists()) {
				throw new PathCreatorException("Cannot find file: " + fullPath);
			}
			if(!file.canRead()) {
				throw new PathCreatorException("Cannot read file: " + fullPath);
			}
			return null;
		} 
		catch (ConfigurationException e) {
			throw new PathCreatorException(e);
		}
		catch (SQLException e) {
			throw new PathCreatorException(e);
		}
		catch (NullPointerException e) {
			throw new PathCreatorException(e);
		} 
	}

	@Override
	public String getExtension(CommandQueueElement el, ResultSet res) {
		
		StringBuilder extension = new StringBuilder();
		try {
			String fileName = res.getString("file_name");
	        int dotIndex = fileName.lastIndexOf(".");
	        if((dotIndex > 0) && (dotIndex < fileName.length())) {
	            extension.append(fileName.substring(dotIndex + 1));
			}

		} catch (SQLException ex) {
			logger.error(ex.toString());
		}
		return extension.toString();
	}
	
}
